<?php

namespace App\Services;

use App\Helpers\MarkerGeometry;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    /**
     * @return array[] Word entries: description, vertices (4-point boundingBox), confidence
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

        Log::debug('OCR raw response', ['json' => $response->body()]);

        $pages = $response->json('responses.0.fullTextAnnotation.pages') ?? [];
        $words = [];

        foreach ($pages as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                foreach ($block['paragraphs'] ?? [] as $paragraph) {
                    foreach ($paragraph['words'] ?? [] as $word) {
                        $confidence = (float) ($word['confidence'] ?? 0.0);
                        if ($confidence < config('services.google_cloud_vision.ocr_confidence_threshold')) {
                            continue;
                        }

                        $vertices = $word['boundingBox']['vertices'] ?? [];
                        $text = implode('', array_map(
                            fn (array $symbol) => $symbol['text'] ?? '',
                            $word['symbols'] ?? []
                        ));

                        if ($text === '' || empty($vertices)) {
                            continue;
                        }

                        $words[] = [
                            'description' => $text,
                            'vertices' => $vertices,
                            'confidence' => $confidence,
                        ];
                    }
                }
            }
        }

        return $words;
    }

    /**
     * @param  array[]  $words  Word entries from recognizeFullImage
     * @param  array[]  $markers  Raw marker arrays from ArucoService
     * @return string[]
     */
    private function matchTextToMarkers(array $words, array $markers): array
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

            $rRad = deg2rad((float) $marker['rotation']);
            $cosR = cos($rRad);
            $sinR = sin($rRad);

            $matched = [];
            foreach ($words as $word) {
                $vertices = $word['vertices'];
                $centroidX = array_sum(array_column($vertices, 'x')) / count($vertices);
                $centroidY = array_sum(array_column($vertices, 'y')) / count($vertices);

                if (! MarkerGeometry::pointInHitbox($centroidX, $centroidY, $hitboxCorners)) {
                    continue;
                }

                $localYs = array_map(fn ($v) => -$sinR * (($v['x'] ?? 0) - $cx) + $cosR * (($v['y'] ?? 0) - $cy), $vertices);
                $localXs = array_map(fn ($v) => $cosR * (($v['x'] ?? 0) - $cx) + $sinR * (($v['y'] ?? 0) - $cy), $vertices);

                $matched[] = [
                    'text' => $word['description'],
                    'lineY' => (min($localYs) + max($localYs)) / 2.0,
                    'wordH' => max($localYs) - min($localYs),
                    'startX' => min($localXs),
                ];
            }

            $result = $this->orderWordsIntoLines($matched, $width);

            Log::debug('OCR marker match', [
                'marker_id' => $marker['id'],
                'word_count' => count($matched),
                'words' => array_map(fn ($w) => ['text' => $w['text'], 'lineY' => round($w['lineY'], 1), 'startX' => round($w['startX'], 1)], $matched),
                'result' => $result,
            ]);

            $results[$index] = $result;
        }

        return $results;
    }

    /**
     * Group words into lines by Y proximity, then sort lines top-to-bottom and words left-to-right.
     *
     * @param  array[]  $words  Each entry: text, lineY, wordH, startX (all in marker-local frame)
     */
    private function orderWordsIntoLines(array $words, float $markerWidth): string
    {
        if (empty($words)) {
            return '';
        }

        usort($words, fn ($a, $b) => $a['lineY'] <=> $b['lineY'] ?: $a['startX'] <=> $b['startX']);

        $lines = [];
        foreach ($words as $word) {
            $threshold = max($word['wordH'] * 0.6, $markerWidth * 0.1);
            $placed = false;
            foreach ($lines as &$line) {
                if (abs($word['lineY'] - $line['centerY']) <= $threshold) {
                    $line['words'][] = $word;
                    $line['centerY'] = array_sum(array_column($line['words'], 'lineY')) / count($line['words']);
                    $placed = true;
                    break;
                }
            }
            unset($line);

            if (! $placed) {
                $lines[] = ['centerY' => $word['lineY'], 'words' => [$word]];
            }
        }

        usort($lines, fn ($a, $b) => $a['centerY'] <=> $b['centerY']);

        $ordered = [];
        foreach ($lines as $line) {
            $lineWords = $line['words'];
            usort($lineWords, fn ($a, $b) => $a['startX'] <=> $b['startX']);
            foreach ($lineWords as $w) {
                $ordered[] = $w['text'];
            }
        }

        return implode(' ', $ordered);
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

        $this->blankOutsideHitboxes($img, $markers);

        ob_start();
        imagejpeg($img, null, 95);
        $bytes = ob_get_clean();

        return $bytes;
    }

    private function blankOutsideHitboxes(\GdImage $img, array $markers): void
    {
        $w = imagesx($img);
        $h = imagesy($img);

        $mask = imagecreatetruecolor($w, $h);
        $maskBlack = imagecolorallocate($mask, 0, 0, 0);
        $maskWhite = imagecolorallocate($mask, 255, 255, 255);
        imagefill($mask, 0, 0, $maskBlack);

        foreach ($markers as $marker) {
            $cx = (float) $marker['center']['x'];
            $cy = (float) $marker['center']['y'];
            $corners = $marker['corners'];
            $markerW = MarkerGeometry::euclideanDistance(
                (float) $corners[0]['x'], (float) $corners[0]['y'],
                (float) $corners[1]['x'], (float) $corners[1]['y'],
            );
            $hitbox = MarkerGeometry::resolveHitbox((int) $marker['id']);
            $hitboxCorners = MarkerGeometry::hitboxCorners($cx, $cy, $markerW, $hitbox, (float) $marker['rotation']);

            imagefilledpolygon($mask, [
                (int) round($hitboxCorners[0]['x']), (int) round($hitboxCorners[0]['y']),
                (int) round($hitboxCorners[1]['x']), (int) round($hitboxCorners[1]['y']),
                (int) round($hitboxCorners[2]['x']), (int) round($hitboxCorners[2]['y']),
                (int) round($hitboxCorners[3]['x']), (int) round($hitboxCorners[3]['y']),
            ], $maskWhite);
        }

        $imgWhite = imagecolorallocate($img, 255, 255, 255);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (imagecolorat($mask, $x, $y) === 0) {
                    imagesetpixel($img, $x, $y, $imgWhite);
                }
            }
        }
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
