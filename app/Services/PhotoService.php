<?php

namespace App\Services;

use App\Enums\CornerPosition;
use App\Models\ArucoMarker;
use App\Models\ArucoMarkerCorner;
use App\Models\DetectionResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PhotoService
{
    public function __construct(private ArucoService $arucoService) {}

    public function store(UploadedFile $photo): string
    {
        $filename = now()->timezone('Europe/Amsterdam')->format('Y-m-d_H-i-s_v').'.'.$photo->getClientOriginalExtension();
        $path = $photo->storeAs('photos', $filename, 'local');

        $absolutePath = Storage::disk('local')->path($path);

        try {
            $markers = $this->arucoService->detectMarkers($absolutePath);

            Log::info('[ArUco] Detection complete', [
                'file' => $filename,
                'marker_count' => count($markers),
                'markers' => $markers,
            ]);

            $detectionResult = DetectionResult::create([
                'filename'         => $filename,
                'image_path'       => $path,
                'detection_failed' => false,
                'detected_at'      => Carbon::now(),
            ]);

            foreach ($markers as $marker) {
                $arucoMarker = ArucoMarker::create([
                    'detection_result_id' => $detectionResult->id,
                    'marker_id'           => $marker['id'],
                    'center_x'            => $marker['center']['x'],
                    'center_y'            => $marker['center']['y'],
                    'rotation'            => $marker['rotation'],
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
                        'position'        => $position,
                        'x'               => $marker['corners'][$index]['x'],
                        'y'               => $marker['corners'][$index]['y'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ArUco] Detection failed', [
                'file' => $filename,
                'error' => $e->getMessage(),
            ]);

            DetectionResult::create([
                'filename'         => $filename,
                'image_path'       => $path,
                'detection_failed' => true,
                'detected_at'      => Carbon::now(),
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
}
