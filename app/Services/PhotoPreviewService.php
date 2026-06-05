<?php

namespace App\Services;

use App\Enums\CornerPosition;
use App\Enums\MarkerType;
use App\Enums\PhotoPreviewStatus;
use App\Enums\PhotoStatus;
use App\Jobs\ProcessPhotoPreviewJob;
use App\Models\ArucoMarker;
use App\Models\ArucoMarkerCorner;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;
use App\Models\Photo;
use App\Models\PhotoPreview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PhotoPreviewService
{
    public function __construct(
        private ArucoService $arucoService,
        private ImageSnippetService $imageSnippetService,
        private OcrService $ocrService,
        private EdgeDetectionService $edgeDetectionService,
        private VueFlowConversionService $vueFlowConversionService,
    ) {}

    public function startPreview(UploadedFile $photo, int $userId): PhotoPreview
    {
        $this->deleteUserPreviews($userId);

        $filename = now()->timezone('Europe/Amsterdam')->format('Y-m-d_H-i-s_v').'.'.$photo->getClientOriginalExtension();
        $path = $photo->storeAs('photos/previews', $filename, 'local');

        $preview = PhotoPreview::create([
            'user_id' => $userId,
            'file_path' => $path,
            'status' => PhotoPreviewStatus::Detecting,
            'expires_at' => now()->addMinutes(30),
        ]);

        ProcessPhotoPreviewJob::dispatch($preview);

        return $preview;
    }

    public function runDetection(PhotoPreview $preview): void
    {
        $absolutePath = Storage::disk('local')->path($preview->file_path);

        try {
            $this->imageSnippetService->normalizeExifOrientation($absolutePath);

            $markers = $this->arucoService->detectMarkers($absolutePath);

            $filename = basename($preview->file_path);

            $detectionResult = DetectionResult::create([
                'filename' => $filename,
                'image_path' => $preview->file_path,
                'detection_failed' => false,
                'detected_at' => Carbon::now(),
            ]);

            $ocrTexts = $this->ocrService->recognizeTextInRegions($absolutePath, $markers);

            $cornerMap = [
                CornerPosition::TopLeft,
                CornerPosition::TopRight,
                CornerPosition::BottomRight,
                CornerPosition::BottomLeft,
            ];

            $markerConfig = config('marker_config', []);
            $nodeCount = 0;

            foreach ($markers as $index => $marker) {
                if (MarkerType::fromConfig($marker['id'], $markerConfig) === MarkerType::Node) {
                    $nodeCount++;
                }

                $arucoMarker = ArucoMarker::create([
                    'detection_result_id' => $detectionResult->id,
                    'marker_id' => $marker['id'],
                    'center_x' => $marker['center']['x'],
                    'center_y' => $marker['center']['y'],
                    'rotation' => $marker['rotation'],
                    'ocr_text' => $ocrTexts[$index],
                ]);

                foreach ($cornerMap as $cornerIndex => $position) {
                    ArucoMarkerCorner::create([
                        'aruco_marker_id' => $arucoMarker->id,
                        'position' => $position,
                        'x' => $marker['corners'][$cornerIndex]['x'],
                        'y' => $marker['corners'][$cornerIndex]['y'],
                    ]);
                }
            }

            $persistedMarkers = $detectionResult->markers()->with('corners')->get();
            $edges = $this->edgeDetectionService->detectEdges($persistedMarkers);

            foreach ($edges as $edge) {
                DetectedEdge::create([
                    'detection_result_id' => $detectionResult->id,
                    'edge_marker_id' => $edge['edge_marker']->id,
                    'source_marker_id' => $edge['source_marker']->id,
                    'target_marker_id' => $edge['target_marker']->id,
                    'edge_type' => $edge['edge_type']->value,
                    'retry_attempts' => $edge['retry_attempts'],
                ]);
            }

            $preview->update([
                'status' => PhotoPreviewStatus::Detected,
                'nodes_count' => $nodeCount,
                'edges_count' => count($edges),
                'detection_result_id' => $detectionResult->id,
            ]);
        } catch (Throwable $e) {
            report($e);
            $preview->update(['status' => PhotoPreviewStatus::Failed]);
        }
    }

    public function commit(PhotoPreview $preview, ?int $projectId, int $userId): Photo
    {
        $filename = basename($preview->file_path);
        $permanentPath = 'photos/'.$filename;

        Storage::disk('local')->move($preview->file_path, $permanentPath);

        $photo = Photo::create([
            'project_id' => $projectId,
            'filename' => $filename,
            'path' => $permanentPath,
            'status' => PhotoStatus::Completed,
        ]);

        $detectionResult = $preview->detectionResult;

        if ($detectionResult) {
            $detectionResult->update(['image_path' => $permanentPath]);

            $sketch = $this->vueFlowConversionService->convert($detectionResult, $projectId);
            $photo->update(['sketch_id' => $sketch->id]);
        }

        $preview->delete();

        return $photo;
    }

    public function deletePreview(PhotoPreview $preview): void
    {
        Storage::disk('local')->delete($preview->file_path);
        $preview->delete();
    }

    public function cleanupExpired(): void
    {
        PhotoPreview::where('expires_at', '<', now())
            ->each(fn (PhotoPreview $preview) => $this->deletePreview($preview));
    }

    private function deleteUserPreviews(int $userId): void
    {
        PhotoPreview::where('user_id', $userId)
            ->each(fn (PhotoPreview $preview) => $this->deletePreview($preview));
    }
}
