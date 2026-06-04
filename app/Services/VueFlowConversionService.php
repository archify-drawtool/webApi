<?php

namespace App\Services;

use App\Enums\MarkerType;
use App\Helpers\MarkerGeometry;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;
use App\Models\Sketch;
class VueFlowConversionService
{
    public function convert(DetectionResult $detectionResult, ?int $projectId, ?int $userId = null): Sketch
    {
        $detectionResult->loadMissing([
            'markers.corners',
            'edges.edgeMarker',
            'edges.sourceMarker',
            'edges.targetMarker',
        ]);

        $markerConfig = config('marker_config', []);
        $nodeTypes = collect(config('node_types'));

        $markerSizes = [];
        $nodes = $detectionResult->markers
            ->filter(fn ($marker) => MarkerType::fromConfig($marker->marker_id, $markerConfig) === MarkerType::Node)
            ->map(function ($marker) use ($nodeTypes, &$markerSizes) {
                $nodeType = $nodeTypes->firstWhere('aruco', $marker->marker_id);
                $position = MarkerGeometry::markerHitboxCenter($marker);

                $size = MarkerGeometry::markerEffectiveSize($marker);
                if ($size > 0) {
                    $markerSizes[] = $size;
                }

                return [
                    'id' => 'node-'.$marker->id,
                    'type' => $nodeType['type'] ?? 'rectangle',
                    'position' => $position,
                    'data' => [
                        'label' => $marker->ocr_text ?? '',
                        'icon' => $nodeType['icon'] ?? 'square',
                    ],
                ];
            })
            ->values()
            ->all();

        $avgMarkerSize = count($markerSizes) > 0
            ? array_sum($markerSizes) / count($markerSizes)
            : 0.0;

        $nodes = $this->normalizePositions($nodes, $avgMarkerSize);

        $nodePositions = collect($nodes)
            ->keyBy(fn ($n) => (int) str_replace('node-', '', $n['id']))
            ->map(fn ($n) => $n['position'])
            ->all();

        $edges = $detectionResult->edges
            ->map(fn ($edge) => $this->buildEdge($edge, $nodePositions))
            ->all();

        return Sketch::create([
            'title' => 'Foto-schets '.now()->format('d-m-Y'),
            'project_id' => $projectId,
            'created_by' => $userId,
            'canvas_state' => ['nodes' => $nodes, 'edges' => $edges],
        ]);
    }

    /**
     * Shift node positions so the bounding box starts at (0, 0), then scale
     * uniformly so that one marker-width maps to 150 canvas units. This keeps
     * relative spacing intact: close-up shots produce compact layouts while
     * wide shots produce spacious ones — without stretching to fixed bounds.
     *
     * @param  array[]  $nodes
     * @param  float  $avgMarkerSize  average marker side length in pixels
     * @return array[]
     */
    private function normalizePositions(array $nodes, float $avgMarkerSize): array
    {
        if (count($nodes) < 1) {
            return $nodes;
        }

        $positions = array_column($nodes, 'position');
        $xs = array_column($positions, 'x');
        $ys = array_column($positions, 'y');

        $minX = min($xs);
        $minY = min($ys);

        $scaleDenominator = (float) config('canvas.scale_denominator', 75.0);
        $scale = $avgMarkerSize > 0 ? $scaleDenominator / $avgMarkerSize : 1.0;

        return array_map(function (array $node) use ($minX, $minY, $scale) {
            $node['position']['x'] = ($node['position']['x'] - $minX) * $scale;
            $node['position']['y'] = ($node['position']['y'] - $minY) * $scale;

            return $node;
        }, $nodes);
    }

    private function buildEdge(DetectedEdge $edge, array $nodePositions): array
    {
        $edgeType = $edge->edge_type instanceof MarkerType
            ? $edge->edge_type->value
            : $edge->edge_type;

        $srcPos = $nodePositions[$edge->source_marker_id] ?? null;
        $tgtPos = $nodePositions[$edge->target_marker_id] ?? null;

        $sourceHandle = null;
        $targetHandle = null;

        if ($srcPos && $tgtPos) {
            $dx = $tgtPos['x'] - $srcPos['x'];
            $dy = $tgtPos['y'] - $srcPos['y'];

            $srcSide = $this->dominantSide($dx, $dy);
            $tgtSide = $this->dominantSide(-$dx, -$dy);

            [$srcRole, $tgtRole] = match ($edgeType) {
                MarkerType::Directionless->value => ['source', 'source'],
                MarkerType::Bidirectional->value => ['target', 'target'],
                default => ['source', 'target'],
            };

            $sourceHandle = "{$srcSide}-{$srcRole}";
            $targetHandle = "{$tgtSide}-{$tgtRole}";
        }

        $vfEdge = [
            'id' => 'edge-'.$edge->id,
            'source' => 'node-'.$edge->source_marker_id,
            'target' => 'node-'.$edge->target_marker_id,
            'sourceHandle' => $sourceHandle,
            'targetHandle' => $targetHandle,
            'label' => $edge->edgeMarker->ocr_text ?? '',
            'data' => [
                'edgeType' => $edgeType,
            ],
        ];

        if ($edgeType === MarkerType::Monodirectional->value) {
            $vfEdge['markerEnd'] = ['type' => 'arrowclosed'];
        } elseif ($edgeType === MarkerType::Bidirectional->value) {
            $vfEdge['markerStart'] = ['type' => 'arrowclosed'];
            $vfEdge['markerEnd'] = ['type' => 'arrowclosed'];
        }

        return $vfEdge;
    }

    private function dominantSide(float $dx, float $dy): string
    {
        if (abs($dx) >= abs($dy)) {
            return $dx >= 0 ? 'right' : 'left';
        }

        return $dy >= 0 ? 'bottom' : 'top';
    }
}
