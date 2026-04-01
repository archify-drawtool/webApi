<?php

namespace App\Services;

use App\Enums\CornerPosition;
use App\Models\ArucoMarker;
use App\Models\ArucoMarkerCorner;
use App\Models\DetectionResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

readonly class PhotoService
{
    public function __construct(
        private ArucoService $arucoService,
        private ImageSnippetService $imageSnippetService,
        private OcrService $ocrService,
    ) {}

    public function store(UploadedFile $photo): string
    {
        $filename = now()->timezone('Europe/Amsterdam')->format('Y-m-d_H-i-s_v').'.'.$photo->getClientOriginalExtension();
        $path = $photo->storeAs('photos', $filename, 'local');

        $absolutePath = Storage::disk('local')->path($path);

        try {
            $markers = $this->arucoService->detectMarkers($absolutePath);

            $detectionResult = DetectionResult::create([
                'filename' => $filename,
                'image_path' => $path,
                'detection_failed' => false,
                'detected_at' => Carbon::now(),
            ]);

            foreach ($markers as $marker) {
                $arucoMarker = ArucoMarker::create([
                    'detection_result_id' => $detectionResult->id,
                    'marker_id' => $marker['id'],
                    'center_x' => $marker['center']['x'],
                    'center_y' => $marker['center']['y'],
                    'rotation' => $marker['rotation'],
                    'ocr_text' => $this->extractOcrText($absolutePath, $marker, $detectionResult->id),
                ]);

                $cornerMap = [
                    CornerPosition::TopLeft,
                    CornerPosition::TopRight,
                    CornerPosition::BottomRight,
                    CornerPosition::BottomLeft,
                ];

                foreach ($cornerMap as $index => $position) {
                    ArucoMarkerCorner::create([
                        'aruco_marker_id' => $arucoMarker->id,
                        'position' => $position,
                        'x' => $marker['corners'][$index]['x'],
                        'y' => $marker['corners'][$index]['y'],
                    ]);
                }
            }
        } catch (Throwable $e) {
            DetectionResult::create([
                'filename' => $filename,
                'image_path' => $path,
                'detection_failed' => true,
                'detected_at' => Carbon::now(),
            ]);
        }

        return $path;
    }

    public function getDetectionResult(string $filename): ?DetectionResult
    {
        return DetectionResult::with('markers.corners')
            ->where('filename', $filename)
            ->first();
    }

    private function extractOcrText(string $absolutePath, array $marker, int $detectionResultId): ?string
    {
        try {
            $imageData = $this->imageSnippetService->extractSnippet($absolutePath, $marker);

            // TODO: store for debugging.
            Storage::disk('local')->put(
                "debug/ocr-snippets/{$detectionResultId}_{$marker['id']}.jpg",
                $imageData
            );

            return $this->ocrService->recognizeTextFromImageData($imageData);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
