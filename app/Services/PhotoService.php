<?php

namespace App\Services;

use App\Enums\CornerPosition;
use App\Models\ArucoMarker;
use App\Models\ArucoMarkerCorner;
use App\Models\DetectedEdge;
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
        private EdgeDetectionService $edgeDetectionService,
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

            $snippets = $this->extractSnippets($absolutePath, $markers);
            $ocrTexts = $this->batchOcr($snippets);

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

            $persistedMarkers = $detectionResult->markers()->get();
            $edges = $this->edgeDetectionService->detectEdges($persistedMarkers);
            foreach ($edges as $edge) {
                DetectedEdge::create([
                    'detection_result_id' => $detectionResult->id,
                    'edge_marker_id' => $edge['edge_marker']->id,
                    'source_marker_id' => $edge['source_marker']->id,
                    'target_marker_id' => $edge['target_marker']->id,
                    'edge_type' => $edge['edge_type']->value,
                ]);
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
        return DetectionResult::with([
            'markers.corners',
            'edges.edgeMarker',
            'edges.sourceMarker',
            'edges.targetMarker',
        ])->where('filename', $filename)->first();
    }

    /**
     * Extract image snippets for all markers. Entries that fail are stored as null.
     *
     * @param  array[]  $markers
     * @return (string|null)[]
     */
    private function extractSnippets(string $absolutePath, array $markers): array
    {
        return array_map(function (array $marker) use ($absolutePath): ?string {
            try {
                return $this->imageSnippetService->extractSnippet($absolutePath, $marker);
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        }, $markers);
    }

    /**
     * Send all non-null snippets to Vision in one request, returning an indexed array of
     * OCR text strings (or null for markers whose snippet extraction failed).
     *
     * @param  (string|null)[]  $snippets
     * @return (string|null)[]
     */
    private function batchOcr(array $snippets): array
    {
        $indexedSnippets = array_filter($snippets, fn (?string $s) => $s !== null);

        if (empty($indexedSnippets)) {
            return array_fill(0, count($snippets), null);
        }

        $originalIndices = array_keys($indexedSnippets);

        try {
            $results = $this->ocrService->recognizeTextBatch(array_values($indexedSnippets));
        } catch (Throwable $e) {
            report($e);
            $results = [];
        }

        $ocrTexts = array_fill(0, count($snippets), null);
        foreach ($originalIndices as $batchIndex => $originalIndex) {
            $ocrTexts[$originalIndex] = $results[$batchIndex] ?? null;
        }

        return $ocrTexts;
    }
}
