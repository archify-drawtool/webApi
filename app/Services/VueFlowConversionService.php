<?php

namespace App\Services;

use App\Enums\MarkerType;
use App\Helpers\MarkerGeometry;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;
use App\Models\Sketch;
use Illuminate\Support\Facades\Auth;

class VueFlowConversionService
{
    public function convert(DetectionResult $detectionResult, int $projectId): Sketch
    {
        $detectionResult->loadMissing([
            'markers.corners',
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
                $position = MarkerGeometry::markerHitboxCenter($marker);

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

        $nodes = $this->normalizePositions($nodes);

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

        $canvasMinX = (float) config('canvas.min_x', 100.0);
        $canvasMaxX = (float) config('canvas.max_x', 1300.0);
        $canvasMinY = (float) config('canvas.min_y', 100.0);
        $canvasMaxY = (float) config('canvas.max_y', 700.0);

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

    private function dominantSide(float $dx, float $dy): string
    {
        if (abs($dx) >= abs($dy)) {
            return $dx >= 0 ? 'right' : 'left';
        }

        return $dy >= 0 ? 'bottom' : 'top';
    }
}
