<?php

namespace App\Services;

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
