<?php

namespace App\Services;

use App\Enums\MarkerType;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;
use App\Models\Sketch;
use Illuminate\Support\Facades\Auth;

class VueFlowConversionService
{
    public function convert(DetectionResult $detectionResult, int $projectId): Sketch
    {
        $detectionResult->loadMissing([
            'markers',
            'edges.edgeMarker',
            'edges.sourceMarker',
            'edges.targetMarker',
        ]);

        $markerConfig = config('marker_config', []);
        $nodeTypes = collect(config('node_types'));

        $nodes = $detectionResult->markers
            ->filter(fn ($marker) => MarkerType::fromConfig($marker->marker_id, $markerConfig) === MarkerType::Node)
            ->map(function ($marker) use ($nodeTypes) {
                $nodeType = $nodeTypes->firstWhere('aruco', $marker->marker_id);

                return [
                    'id' => 'node-'.$marker->id,
                    'type' => $nodeType['type'] ?? 'rectangle',
                    'position' => ['x' => $marker->center_x, 'y' => $marker->center_y],
                    'data' => [
                        'label' => $marker->ocr_text ?? '',
                        'icon' => $nodeType['icon'] ?? 'square',
                    ],
                ];
            })
            ->values()
            ->all();

        $nodes = $this->normalizePositions($nodes);

        $edges = $detectionResult->edges
            ->map(fn ($edge) => $this->buildEdge($edge))
            ->all();

        return Sketch::create([
            'title' => 'Foto-schets '.now()->format('d-m-Y'),
            'project_id' => $projectId,
            'created_by' => Auth::id(),
            'canvas_state' => ['nodes' => $nodes, 'edges' => $edges],
        ]);
    }

    /**
     * Scale all node positions uniformly so they fit within 1400×800,
     * then offset them so the bounding box is centered on (700, 400).
     *
     * @param  array[]  $nodes
     * @return array[]
     */
    private function normalizePositions(array $nodes): array
    {
        if (count($nodes) < 2) {
            return $nodes;
        }

        $positions = array_column($nodes, 'position');
        $xs = array_column($positions, 'x');
        $ys = array_column($positions, 'y');

        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);

        $rangeX = $maxX - $minX;
        $rangeY = $maxY - $minY;

        if ($rangeX == 0 && $rangeY == 0) {
            return $nodes;
        }

        // Rotate portrait layouts (taller than wide) 90° clockwise.
        if ($rangeY > $rangeX) {
            $origMaxX = $maxX;
            $nodes = array_map(function (array $node) use ($origMaxX) {
                [$node['position']['x'], $node['position']['y']] = [
                    $node['position']['y'],
                    $origMaxX - $node['position']['x'],
                ];

                return $node;
            }, $nodes);
            // After clockwise rotation: new x = old y, new y = origMaxX - old x
            [$minX, $minY] = [$minY, 0];
            [$rangeX, $rangeY] = [$rangeY, $rangeX];
        }

        $canvasMinX = 100.0;
        $canvasMaxX = 1300.0;
        $canvasMinY = 100.0;
        $canvasMaxY = 700.0;

        $canvasWidth = $canvasMaxX - $canvasMinX;
        $canvasHeight = $canvasMaxY - $canvasMinY;

        $scale = min(
            $rangeX > 0 ? $canvasWidth / $rangeX : PHP_FLOAT_MAX,
            $rangeY > 0 ? $canvasHeight / $rangeY : PHP_FLOAT_MAX,
        );

        // After scaling, the bounding box spans [0, rangeX*scale] × [0, rangeY*scale].
        // Offset so it is centered within the canvas bounds.
        $offsetX = $canvasMinX + ($canvasWidth - $rangeX * $scale) / 2;
        $offsetY = $canvasMinY + ($canvasHeight - $rangeY * $scale) / 2;

        return array_map(function (array $node) use ($minX, $minY, $scale, $offsetX, $offsetY) {
            $node['position']['x'] = ($node['position']['x'] - $minX) * $scale + $offsetX;
            $node['position']['y'] = ($node['position']['y'] - $minY) * $scale + $offsetY;

            return $node;
        }, $nodes);
    }

    private function buildEdge(DetectedEdge $edge): array
    {
        $edgeType = $edge->edge_type instanceof MarkerType
            ? $edge->edge_type->value
            : $edge->edge_type;

        $vfEdge = [
            'id' => 'edge-'.$edge->id,
            'source' => 'node-'.$edge->source_marker_id,
            'target' => 'node-'.$edge->target_marker_id,
            'label' => $edge->edgeMarker->ocr_text ?? '',
            'data' => ['edgeType' => $edgeType],
        ];

        if ($edgeType === MarkerType::Monodirectional->value) {
            $vfEdge['markerEnd'] = ['type' => 'arrowclosed'];
        } elseif ($edgeType === MarkerType::Bidirectional->value) {
            $vfEdge['markerStart'] = ['type' => 'arrowclosed'];
            $vfEdge['markerEnd'] = ['type' => 'arrowclosed'];
        }

        return $vfEdge;
    }
}
