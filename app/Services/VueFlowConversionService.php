<?php

namespace App\Services;

use App\Enums\MarkerType;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;
use App\Models\Sketch;

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
            ->filter(fn ($marker) => $this->isNodeMarker($marker->marker_id, $markerConfig))
            ->map(function ($marker) use ($nodeTypes) {
                $nodeType = $nodeTypes->firstWhere('aruco', $marker->marker_id);

                return [
                    'id'       => 'node-'.$marker->id,
                    'type'     => $nodeType['type'] ?? 'rectangle',
                    'position' => ['x' => $marker->center_x, 'y' => $marker->center_y],
                    'data'     => [
                        'label' => $marker->ocr_text ?? '',
                        'icon'  => $nodeType['icon'] ?? 'square',
                    ],
                ];
            })
            ->values()
            ->all();

        $edges = $detectionResult->edges
            ->map(fn ($edge) => $this->buildEdge($edge))
            ->all();

        return Sketch::create([
            'title'        => 'Foto-schets '.now()->format('d-m-Y'),
            'project_id'   => $projectId,
            'created_by'   => auth()->id(),
            'canvas_state' => ['nodes' => $nodes, 'edges' => $edges],
        ]);
    }

    private function isNodeMarker(int $markerId, array $config): bool
    {
        $entry = $config[$markerId] ?? null;

        return $entry === null || $entry['type'] === MarkerType::Node->value;
    }

    private function buildEdge(DetectedEdge $edge): array
    {
        $edgeType = $edge->edge_type instanceof MarkerType
            ? $edge->edge_type->value
            : $edge->edge_type;

        $vfEdge = [
            'id'     => 'edge-'.$edge->id,
            'source' => 'node-'.$edge->source_marker_id,
            'target' => 'node-'.$edge->target_marker_id,
            'label'  => $edge->edgeMarker->ocr_text ?? '',
            'data'   => ['edgeType' => $edgeType],
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
