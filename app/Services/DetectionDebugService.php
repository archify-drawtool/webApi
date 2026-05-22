<?php

namespace App\Services;

use App\Helpers\MarkerGeometry;
use App\Models\ArucoMarker;
use App\Models\ArucoMarkerCorner;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;

class DetectionDebugService
{
    public function buildPayload(DetectionResult $result): array
    {
        $markerConfig = config('marker_config');
        $marginFactor = (float) config('aruco.edge_margin');
        $angleDeg = (float) config('aruco.edge_angle_margin');

        $edgeByMarkerId = $result->edges->keyBy('edge_marker_id');

        $nodeMarkers = [];
        $edgeMarkers = [];

        foreach ($result->markers as $marker) {
            $cfg = $markerConfig[$marker->marker_id] ?? [
                'type' => 'node',
                'hitbox' => ['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0],
            ];

            $base = [
                'id' => $marker->id,
                'marker_id' => $marker->marker_id,
                'center_x' => $marker->center_x,
                'center_y' => $marker->center_y,
                'rotation' => $marker->rotation,
                'ocr_text' => $marker->ocr_text,
                'corners' => $marker->corners->map(fn (ArucoMarkerCorner $c) => [
                    'position' => $c->position,
                    'x' => $c->x,
                    'y' => $c->y,
                ])->values(),
                'type' => $cfg['type'],
                'hitbox' => $cfg['hitbox'],
                'hitbox_corners' => $this->hitboxCorners($marker, $cfg['hitbox']),
            ];

            if ($cfg['type'] === 'node') {
                $nodeMarkers[] = $base;
            } else {
                $detectedEdge = $edgeByMarkerId->get($marker->id);
                $base['detection_lines'] = $this->detectionLinesForMarker(
                    $marker,
                    $detectedEdge?->sourceMarker,
                    $detectedEdge?->targetMarker,
                    $marginFactor,
                    $angleDeg
                );
                $edgeMarkers[] = $base;
            }
        }

        $edges = $result->edges->map(fn (DetectedEdge $edge) => [
            'id' => $edge->id,
            'edge_type' => $edge->edge_type,
            'edge_marker_id' => $edge->edge_marker_id,
            'source_marker_id' => $edge->source_marker_id,
            'target_marker_id' => $edge->target_marker_id,
        ])->values();

        return [
            'node_markers' => array_values($nodeMarkers),
            'edge_markers' => array_values($edgeMarkers),
            'edges' => $edges,
            'config' => [
                'edge_margin' => $marginFactor,
                'edge_angle_margin' => $angleDeg,
            ],
        ];
    }

    private function hitboxCorners(ArucoMarker $marker, array $hitbox): array
    {
        $w = MarkerGeometry::markerDimensions($marker->corners)['width'];

        return MarkerGeometry::hitboxCorners(
            $marker->center_x, $marker->center_y,
            $w, $hitbox, $marker->rotation
        );
    }

    private function detectionLinesForMarker(
        ArucoMarker $em,
        ?ArucoMarker $src,
        ?ArucoMarker $tgt,
        float $marginFactor,
        float $angleDeg
    ): array {
        $rRad = deg2rad($em->rotation);
        $mainX = cos($rRad);
        $mainY = sin($rRad);
        $perpX = -sin($rRad);
        $perpY = cos($rRad);

        $markerWidth = MarkerGeometry::markerDimensions($em->corners)['width'];
        $baseMargin = $marginFactor * $markerWidth;
        $angleTan = tan(deg2rad($angleDeg));

        $fallback = 9999;

        $dSrc = $src !== null
            ? ($src->center_x - $em->center_x) * $mainX + ($src->center_y - $em->center_y) * $mainY
            : -$fallback;

        $dTgt = $tgt !== null
            ? ($tgt->center_x - $em->center_x) * $mainX + ($tgt->center_y - $em->center_y) * $mainY
            : $fallback;

        $marginSrc = $baseMargin + $angleTan * abs($dSrc);
        $marginTgt = $baseMargin + $angleTan * abs($dTgt);

        $pt = fn (float $x, float $y): array => ['x' => round($x, 2), 'y' => round($y, 2)];

        $mainStart = $pt($em->center_x + $dSrc * $mainX, $em->center_y + $dSrc * $mainY);
        $mainEnd = $pt($em->center_x + $dTgt * $mainX, $em->center_y + $dTgt * $mainY);

        return [
            'main_start' => $mainStart,
            'main_end' => $mainEnd,
            'center' => $pt($em->center_x, $em->center_y),
            'upper' => [
                'origin' => $pt($em->center_x + $baseMargin * $perpX, $em->center_y + $baseMargin * $perpY),
                'far_start' => $pt($mainStart['x'] + $marginSrc * $perpX, $mainStart['y'] + $marginSrc * $perpY),
                'far_end' => $pt($mainEnd['x'] + $marginTgt * $perpX, $mainEnd['y'] + $marginTgt * $perpY),
            ],
            'lower' => [
                'origin' => $pt($em->center_x - $baseMargin * $perpX, $em->center_y - $baseMargin * $perpY),
                'far_start' => $pt($mainStart['x'] - $marginSrc * $perpX, $mainStart['y'] - $marginSrc * $perpY),
                'far_end' => $pt($mainEnd['x'] - $marginTgt * $perpX, $mainEnd['y'] - $marginTgt * $perpY),
            ],
        ];
    }
}
