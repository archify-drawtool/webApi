<?php

namespace App\Services;

use App\Helpers\MarkerGeometry;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OcrService
{
    private const string VISION_API_URL = 'https://vision.googleapis.com/v1/images:annotate';

    public function recognizeText(UploadedFile $photo): string
    {
        return $this->callVisionApi([base64_encode(file_get_contents($photo->getRealPath()))])[0];
    }

    public function recognizeTextFromImageData(string $imageData): string
    {
        return $this->callVisionApi([base64_encode($imageData)])[0];
    }

    /**
     * Send multiple images to the Vision API in a single request.
     * Returns an array of OCR text strings in the same order as the input.
     * Entries where Vision returned an error are returned as ''.
     *
     * @param  string[]  $imageDataItems  Raw (non-encoded) image bytes
     * @return string[]
     */
    public function recognizeTextBatch(array $imageDataItems): array
    {
        if (empty($imageDataItems)) {
            return [];
        }

        return $this->callVisionApi(array_map('base64_encode', $imageDataItems));
    }

    /**
     * Run DOCUMENT_TEXT_DETECTION on a full image and return per-marker OCR text.
     * Makes exactly one Vision API call regardless of marker count.
     *
     * @param  array[]  $markers  Raw marker arrays from ArucoService (id, center, corners, rotation)
     * @return string[] Indexed by marker order; empty string when no text falls in the hitbox
     */
    public function recognizeTextInRegions(string $imagePath, array $markers): array
    {
        if (empty($markers)) {
            return [];
        }

        $textAnnotations = $this->recognizeFullImage($imagePath, $markers);

        return $this->matchTextToMarkers($textAnnotations, $markers);
    }

    private const float OCR_CONFIDENCE_THRESHOLD = 0.5;

    /**
     * @return array[] Normalized word entries: description, boundingPoly.vertices, confidence
     */
    private function recognizeFullImage(string $imagePath, array $markers): array
    {
        $base64 = base64_encode($this->blankMarkers($imagePath, $markers));
        $apiKey = config('services.google_cloud_vision.api_key');

        try {
            $response = Http::post(self::VISION_API_URL.'?key='.$apiKey, [
                'requests' => [[
                    'image' => ['content' => $base64],
                    'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                    'imageContext' => [
                        'languageHints' => ['en', 'nl'],
                        'textDetectionParams' => ['enableTextDetectionConfidenceScore' => true],
                    ],
                ]],
            ]);

            $response->throw();
        } catch (RequestException $e) {
            throw new HttpException(502, 'Google Cloud Vision API error: '.$e->response->status());
        }

        $pages = $response->json('responses.0.fullTextAnnotation.pages') ?? [];
        $words = [];

        foreach ($pages as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                foreach ($block['paragraphs'] ?? [] as $paragraph) {
                    foreach ($paragraph['words'] ?? [] as $word) {
                        $text = implode('', array_map(
                            fn (array $symbol) => $symbol['text'] ?? '',
                            $word['symbols'] ?? []
                        ));
                        $words[] = [
                            'description' => $text,
                            'boundingPoly' => ['vertices' => $word['boundingBox']['vertices'] ?? []],
                            'confidence' => (float) ($word['confidence'] ?? 0.0),
                        ];
                    }
                }
            }
        }

        return $words;
    }

    /**
     * @param  array[]  $textAnnotations  Word-level Vision blocks (description + boundingPoly.vertices)
     * @param  array[]  $markers  Raw marker arrays from ArucoService
     * @return string[]
     */
    private function matchTextToMarkers(array $textAnnotations, array $markers): array
    {
        $results = array_fill(0, count($markers), '');

        foreach ($markers as $index => $marker) {
            $cx = (float) $marker['center']['x'];
            $cy = (float) $marker['center']['y'];
            $corners = $marker['corners'];

            $width = MarkerGeometry::euclideanDistance(
                (float) $corners[0]['x'], (float) $corners[0]['y'],
                (float) $corners[1]['x'], (float) $corners[1]['y'],
            );

            $hitbox = MarkerGeometry::resolveHitbox((int) $marker['id']);
            $hitboxCorners = MarkerGeometry::hitboxCorners($cx, $cy, $width, $hitbox, (float) $marker['rotation']);

            $rotation = (float) $marker['rotation'];
            $cosR = cos($rotation);
            $sinR = sin($rotation);

            $words = [];
            foreach ($textAnnotations as $block) {
                if (($block['confidence'] ?? 0.0) < self::OCR_CONFIDENCE_THRESHOLD) {
                    continue;
                }

                $vertices = $block['boundingPoly']['vertices'] ?? [];
                if (empty($vertices)) {
                    continue;
                }

                $centroidX = array_sum(array_column($vertices, 'x')) / count($vertices);
                $centroidY = array_sum(array_column($vertices, 'y')) / count($vertices);

                if (MarkerGeometry::pointInHitbox($centroidX, $centroidY, $hitboxCorners)) {
                    $dx = $centroidX - $cx;
                    $dy = $centroidY - $cy;
                    $words[] = [
                        'text' => $block['description'],
                        'localY' => -$sinR * $dx + $cosR * $dy,
                        'localX' => $cosR * $dx + $sinR * $dy,
                    ];
                }
            }

            usort($words, fn ($a, $b) => $a['localY'] <=> $b['localY'] ?: $a['localX'] <=> $b['localX']);

            $results[$index] = implode(' ', array_column($words, 'text'));
        }

        return $results;
    }

    /**
     * Load the image, paint a filled white polygon over every marker's corner quadrilateral,
     * and return the result as raw JPEG bytes. The modified image is never written to disk.
     *
     * @param  array[]  $markers  Raw marker arrays from ArucoService (corners in TL→TR→BR→BL order)
     */
    private function blankMarkers(string $imagePath, array $markers): string
    {
        $info = getimagesize($imagePath);
        $img = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG => imagecreatefrompng($imagePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($imagePath),
            default => throw new \RuntimeException("Unsupported image type for marker blanking: $imagePath"),
        };

        if ($img === false) {
            throw new \RuntimeException("Failed to load image for marker blanking: $imagePath");
        }

        $white = imagecolorallocate($img, 255, 255, 255);

        foreach ($markers as $marker) {
            $c = $marker['corners'];
            imagefilledpolygon($img, [
                (int) round($c[0]['x']), (int) round($c[0]['y']),
                (int) round($c[1]['x']), (int) round($c[1]['y']),
                (int) round($c[2]['x']), (int) round($c[2]['y']),
                (int) round($c[3]['x']), (int) round($c[3]['y']),
            ], $white);
        }

        ob_start();
        imagejpeg($img, null, 95);
        $bytes = ob_get_clean();

        return $bytes;
    }

    /**
     * @param  string[]  $base64Contents
     * @return string[]
     */
    private function callVisionApi(array $base64Contents): array
    {
        $apiKey = config('services.google_cloud_vision.api_key');

        $requests = array_map(fn (string $content) => [
            'image' => ['content' => $content],
            'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
            'imageContext' => ['languageHints' => ['en', 'nl']],
        ], $base64Contents);

        try {
            $response = Http::post(self::VISION_API_URL.'?key='.$apiKey, [
                'requests' => $requests,
            ]);

            $response->throw();
        } catch (RequestException $e) {
            throw new HttpException(502, 'Google Cloud Vision API error: '.$e->response->status());
        }

        return array_map(
            fn (array $item) => $item['fullTextAnnotation']['text'] ?? '',
            $response->json('responses') ?? [],
        );
    }
}
