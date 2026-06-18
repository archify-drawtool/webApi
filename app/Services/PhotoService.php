<?php

namespace App\Services;

use App\Enums\CornerPosition;
use App\Enums\PhotoStatus;
use App\Jobs\ProcessPhotoJob;
use App\Models\ArucoMarker;
use App\Models\ArucoMarkerCorner;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;
use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

readonly class PhotoService
{
    public function __construct(
        private ArucoService $arucoService,
        private ImageSnippetService $imageSnippetService,
        private OcrService $ocrService,
        private EdgeDetectionService $edgeDetectionService,
        private VueFlowConversionService $vueFlowConversionService,
    ) {}

    public function store(UploadedFile $photo, ?int $projectId): Photo
    {
        $filename = now()->timezone('Europe/Amsterdam')->format('Y-m-d_H-i-s_v').'.'.$photo->getClientOriginalExtension();
        $path = $photo->storeAs('photos', $filename, 's3');

        $photoModel = Photo::create([
            'project_id' => $projectId,
            'filename' => $filename,
            'path' => $path,
            'status' => PhotoStatus::Processing,
        ]);

        ProcessPhotoJob::dispatch($photoModel, Auth::id());

        return $photoModel;
    }

    public function process(Photo $photo, ?int $userId = null): void
    {
        // The detection pipeline (Python ArUco subprocess + GD) requires a real
        // local file path, so we pull the S3 object down to a temp file first.
        // Image type is detected from file contents (getimagesize), so the temp
        // file needs no extension.
        $absolutePath = tempnam(sys_get_temp_dir(), 'photo_');
        file_put_contents($absolutePath, Storage::disk('s3')->get($photo->path));

        try {
            // normalizeExifOrientation rewrites the temp file in place. Push the
            // corrected image back to S3 so the stored object matches what we processed.
            $this->imageSnippetService->normalizeExifOrientation($absolutePath);
            Storage::disk('s3')->put($photo->path, file_get_contents($absolutePath));

            $markerConfig = config('marker_config', []);
            $markers = array_values(array_filter(
                $this->arucoService->detectMarkers($absolutePath),
                fn ($m) => array_key_exists($m['id'], $markerConfig),
            ));

            $detectionResult = DetectionResult::create([
                'filename' => $photo->filename,
                'image_path' => $photo->path,
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

            foreach ($markers as $index => $marker) {
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

            $sketch = $this->vueFlowConversionService->convert($detectionResult, $photo->project_id, $userId);

            $photo->update([
                'status' => PhotoStatus::Completed,
                'sketch_id' => $sketch->id,
            ]);
        } catch (Throwable $e) {
            report($e);

            DetectionResult::create([
                'filename' => $photo->filename,
                'image_path' => $photo->path,
                'detection_failed' => true,
                'detected_at' => Carbon::now(),
            ]);

            $photo->update([
                'status' => PhotoStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        } finally {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    public function getDetectionResult(string $filename): ?DetectionResult
    {
        return DetectionResult::with([
            'markers.corners',
            'edges.edgeMarker.corners',
            'edges.sourceMarker',
            'edges.targetMarker',
        ])->where('filename', $filename)->first();
    }

    public function getDetectionResultBySketchId(int $sketchId): ?DetectionResult
    {
        $photo = Photo::where('sketch_id', $sketchId)->first();

        if ($photo === null) {
            return null;
        }

        return $this->getDetectionResult($photo->filename);
    }
}
