<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OcrService
{
    private const VISION_API_URL = 'https://vision.googleapis.com/v1/images:annotate';

    public function recognizeText(UploadedFile $photo): string
    {
        return $this->callVisionApi(base64_encode(file_get_contents($photo->getRealPath())));
    }

    public function recognizeTextFromImageData(string $imageData): string
    {
        return $this->callVisionApi(base64_encode($imageData));
    }

    private function callVisionApi(string $base64Content): string
    {
        $apiKey = config('services.google_cloud_vision.api_key');

        try {
            $response = Http::post(self::VISION_API_URL.'?key='.$apiKey, [
                'requests' => [
                    [
                        'image' => ['content' => $base64Content],
                        'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                        'imageContext' => ['languageHints' => ['en', 'nl']],
                    ],
                ],
            ]);

            $response->throw();
        } catch (RequestException $e) {
            throw new HttpException(502, 'Google Cloud Vision API error: '.$e->response->status());
        }

        return $response->json('responses.0.fullTextAnnotation.text') ?? '';
    }
}
