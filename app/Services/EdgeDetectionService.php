<?php

namespace App\Services;

use App\Enums\MarkerType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class EdgeDetectionService
{
    /**
     * Detect edges between node markers using edge markers as connectors.
     *
     * For each edge marker (type ≠ node), the marker's x-axis defines an imaginary
     * line. The closest node marker on each side of that line forms a connection.
     *
     * Rotation R is stored as clockwise degrees. In image space (y-down), the
     * marker's +x axis unit vector is (cos R_rad, sin R_rad).
     *
     * For every node marker at (nx, ny):
     *   dx  = nx - edge_cx
     *   dy  = ny - edge_cy
     *   dot  =  dx*cos(R) + dy*sin(R)   ← signed distance along +x axis
     *   perp = |−dx*sin(R) + dy*cos(R)| ← perpendicular distance
     *
     * Nodes with perp > edge_margin are off-axis and ignored.
     * The node with the smallest |dot| on each side is selected.
     * source = negative-x node, target = positive-x node.
     *
     * @param  Collection  $persistedMarkers  ArucoMarker Eloquent models with id, marker_id, center_x, center_y, rotation.
     * @return array[] Array of edge data arrays, each with keys:
     *                 edge_marker, source_marker, target_marker, edge_type (MarkerType).
     */
    public function detectEdges(Collection $persistedMarkers): array
    {
        $edgeMargin = config('aruco.edge_margin', 20);
        $markerConfig = config('marker_config', []);

        $edgeMarkers = $persistedMarkers->filter(
            fn ($m) => $this->resolveType($m->marker_id, $markerConfig) !== MarkerType::Node
        );

        $nodeMarkers = $persistedMarkers->filter(
            fn ($m) => $this->resolveType($m->marker_id, $markerConfig) === MarkerType::Node
        );

        Log::debug('[EdgeDetection] Starting edge detection', [
            'edge_margin' => $edgeMargin,
            'total_markers' => $persistedMarkers->count(),
            'edge_markers' => $edgeMarkers->map(fn ($m) => ['db_id' => $m->id, 'marker_id' => $m->marker_id])->values(),
            'node_markers' => $nodeMarkers->map(fn ($m) => ['db_id' => $m->id, 'marker_id' => $m->marker_id])->values(),
        ]);

        $edges = [];

        foreach ($edgeMarkers as $edgeMarker) {
            $edgeType = $this->resolveType($edgeMarker->marker_id, $markerConfig);
            $centerX = (float) $edgeMarker->center_x;
            $centerY = (float) $edgeMarker->center_y;

            $rotationRad = deg2rad((float) $edgeMarker->rotation);
            $cosR = cos($rotationRad);
            $sinR = sin($rotationRad);

            Log::debug('[EdgeDetection] Processing edge marker', [
                'db_id' => $edgeMarker->id,
                'marker_id' => $edgeMarker->marker_id,
                'center' => ['x' => $centerX, 'y' => $centerY],
                'rotation_deg' => $edgeMarker->rotation,
                'edge_type' => $edgeType->value,
            ]);

            $bestNeg = null;
            $bestPos = null;
            $bestNegDot = PHP_FLOAT_MAX;
            $bestPosDot = PHP_FLOAT_MAX;

            foreach ($nodeMarkers as $node) {
                $dx = (float) $node->center_x - $centerX;
                $dy = (float) $node->center_y - $centerY;

                $dotProduct = $dx * $cosR + $dy * $sinR;
                $perp = abs($dx * -$sinR + $dy * $cosR); // Projection of node center onto edge y-axis.

                Log::debug('[EdgeDetection]   Node candidate', [
                    'node_db_id' => $node->id,
                    'node_marker' => $node->marker_id,
                    'dx' => round($dx, 2),
                    'dy' => round($dy, 2),
                    'dot' => round($dotProduct, 2),
                    'perp' => round($perp, 2),
                    'within_margin' => $perp <= $edgeMargin,
                ]);

                if ($perp > $edgeMargin) {
                    continue;
                }

                if ($dotProduct > 0 && $dotProduct < $bestPosDot) {
                    $bestPosDot = $dotProduct;
                    $bestPos = $node;
                } elseif ($dotProduct < 0 && abs($dotProduct) < $bestNegDot) {
                    $bestNegDot = abs($dotProduct);
                    $bestNeg = $node;
                }
            }

            Log::debug('[EdgeDetection]   Result', [
                'source' => $bestNeg ? ['db_id' => $bestNeg->id, 'dot' => round(-$bestNegDot, 2)] : null,
                'target' => $bestPos ? ['db_id' => $bestPos->id, 'dot' => round($bestPosDot, 2)] : null,
                'emitting_edge' => $bestNeg !== null && $bestPos !== null,
            ]);

            if ($bestNeg === null || $bestPos === null) {
                continue;
            }

            $edges[] = [
                'edge_marker' => $edgeMarker,
                'source_marker' => $bestNeg,
                'target_marker' => $bestPos,
                'edge_type' => $edgeType,
            ];
        }

        Log::debug('[EdgeDetection] Done', ['edges_found' => count($edges)]);

        return $edges;
    }

    private function resolveType(int $markerId, array $config): MarkerType
    {
        $typeString = $config[$markerId]['type'] ?? MarkerType::Node->value;

        return MarkerType::from($typeString);
    }
}
