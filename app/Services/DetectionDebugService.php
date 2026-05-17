<?php

namespace App\Services;

use App\Enums\CornerPosition;
use App\Models\ArucoMarker;
use App\Models\ArucoMarkerCorner;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;
use Illuminate\Support\Collection;

class DetectionDebugService
{
    public function buildPayload(DetectionResult $result): array
    {
        $markerConfig = config('marker_config');
        $marginFactor = (float) config('aruco.edge_margin');
        $angleDeg     = (float) config('aruco.edge_angle_margin');

        $markers = $result->markers->map(function (ArucoMarker $marker) use ($markerConfig) {
            $cfg = $markerConfig[$marker->marker_id] ?? [
                'type'   => 'node',
                'hitbox' => ['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0],
            ];

            return [
                'id'             => $marker->id,
                'marker_id'      => $marker->marker_id,
                'center_x'       => $marker->center_x,
                'center_y'       => $marker->center_y,
                'rotation'       => $marker->rotation,
                'ocr_text'       => $marker->ocr_text,
                'corners'        => $marker->corners->map(fn (ArucoMarkerCorner $c) => [
                    'position' => $c->position,
                    'x'        => $c->x,
                    'y'        => $c->y,
                ])->values(),
                'type'           => $cfg['type'],
                'hitbox'         => $cfg['hitbox'],
                'hitbox_corners' => $this->hitboxCorners($marker, $cfg['hitbox']),
            ];
        })->values();

        $edges = $result->edges->map(fn (DetectedEdge $edge) => [
            'id'               => $edge->id,
            'edge_type'        => $edge->edge_type,
            'edge_marker_id'   => $edge->edge_marker_id,
            'source_marker_id' => $edge->source_marker_id,
            'target_marker_id' => $edge->target_marker_id,
            'detection_lines'  => $this->detectionLines($edge, $marginFactor, $angleDeg),
        ])->values();

        return [
            'markers' => $markers,
            'edges'   => $edges,
            'config'  => [
                'edge_margin'       => $marginFactor,
                'edge_angle_margin' => $angleDeg,
            ],
        ];
    }

    private function markerWidthPx(Collection $corners): float
    {
        $tl = $corners->firstWhere('position', CornerPosition::TopLeft);
        $tr = $corners->firstWhere('position', CornerPosition::TopRight);

        return sqrt(($tr->x - $tl->x) ** 2 + ($tr->y - $tl->y) ** 2);
    }

    private function hitboxCorners(ArucoMarker $marker, array $hitbox): array
    {
        $w    = $this->markerWidthPx($marker->corners);
        $rRad = deg2rad($marker->rotation);
        $cosR = cos($rRad);
        $sinR = sin($rRad);

        $local = [
            [-(0.5 + $hitbox['xNeg']) * $w, -(0.5 + $hitbox['yNeg']) * $w],
            [ (0.5 + $hitbox['xPos']) * $w, -(0.5 + $hitbox['yNeg']) * $w],
            [ (0.5 + $hitbox['xPos']) * $w,  (0.5 + $hitbox['yPos']) * $w],
            [-(0.5 + $hitbox['xNeg']) * $w,  (0.5 + $hitbox['yPos']) * $w],
        ];

        return array_map(fn ($lc) => [
            'x' => round($marker->center_x + $lc[0] * $cosR - $lc[1] * $sinR, 2),
            'y' => round($marker->center_y + $lc[0] * $sinR + $lc[1] * $cosR, 2),
        ], $local);
    }

    private function detectionLines(DetectedEdge $edge, float $marginFactor, float $angleDeg): array
    {
        $src   = $edge->sourceMarker;
        $tgt   = $edge->targetMarker;
        $inset = $marginFactor * $this->markerWidthPx($edge->edgeMarker->corners);

        $dx     = $tgt->center_x - $src->center_x;
        $dy     = $tgt->center_y - $src->center_y;
        $length = sqrt($dx * $dx + $dy * $dy);

        if ($length < 1e-6) {
            $seg = ['x1' => $src->center_x, 'y1' => $src->center_y, 'x2' => $src->center_x, 'y2' => $src->center_y];

            return ['center' => $seg, 'upper' => $seg, 'lower' => $seg];
        }

        $ux = $dx / $length;
        $uy = $dy / $length;
        $s  = ['x' => $src->center_x + $inset * $ux, 'y' => $src->center_y + $inset * $uy];
        $t  = ['x' => $tgt->center_x - $inset * $ux, 'y' => $tgt->center_y - $inset * $uy];

        $theta  = deg2rad($angleDeg);
        $tUpper = $this->rotateAround($t, $s, -$theta);
        $tLower = $this->rotateAround($t, $s, $theta);

        $seg = fn ($end) => [
            'x1' => round($s['x'], 2), 'y1' => round($s['y'], 2),
            'x2' => $end['x'],         'y2' => $end['y'],
        ];

        return ['center' => $seg($t), 'upper' => $seg($tUpper), 'lower' => $seg($tLower)];
    }

    private function rotateAround(array $point, array $center, float $radians): array
    {
        $rx = $point['x'] - $center['x'];
        $ry = $point['y'] - $center['y'];

        return [
            'x' => round($center['x'] + $rx * cos($radians) - $ry * sin($radians), 2),
            'y' => round($center['y'] + $rx * sin($radians) + $ry * cos($radians), 2),
        ];
    }
}
