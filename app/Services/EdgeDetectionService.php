<?php

namespace App\Services;

use App\Enums\CornerPosition;
use App\Enums\MarkerType;
use Illuminate\Database\Eloquent\Collection;

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
     * The allowed perpendicular tolerance combines two terms:
     *   base_margin = edge_margin_factor × edge_marker_width_px   (scales with photo distance)
     *   angle_margin = tan(edge_angle_margin) × |dot|             (grows with axial distance)
     *   allowed_perp = base_margin + angle_margin
     *
     * Nodes with perp > allowed_perp are off-axis and ignored.
     * The node with the smallest |dot| on each side is selected.
     * source = negative-x node, target = positive-x node.
     *
     * @param  Collection  $persistedMarkers  ArucoMarker Eloquent models with id, marker_id,
     *                                        center_x, center_y, rotation, and eager-loaded corners.
     * @return array[] Array of edge data arrays, each with keys:
     *                 edge_marker, source_marker, target_marker, edge_type (MarkerType).
     */
    public function detectEdges(Collection $persistedMarkers): array
    {
        $edgeMarginFactor = config('aruco.edge_margin', 0.5);
        $angleMarginDeg = config('aruco.edge_angle_margin', 5.0);
        $angleTan = tan(deg2rad($angleMarginDeg));
        $markerConfig = config('marker_config', []);

        [$edgeMarkers, $nodeMarkers] = $this->partitionMarkers($persistedMarkers, $markerConfig);

        $edges = [];

        foreach ($edgeMarkers as $edgeMarker) {
            $edgeType = MarkerType::fromConfig($edgeMarker->marker_id, $markerConfig);
            $centerX = (float) $edgeMarker->center_x;
            $centerY = (float) $edgeMarker->center_y;

            $rotationRad = deg2rad((float) $edgeMarker->rotation);
            $cosR = cos($rotationRad);
            $sinR = sin($rotationRad);

            $markerSize = $this->computeMarkerSize($edgeMarker->corners);
            $baseMarginPx = $edgeMarginFactor * $markerSize;

            [$bestNeg, $bestPos] = $this->findCandidateNodes(
                $nodeMarkers, $centerX, $centerY, $cosR, $sinR, $baseMarginPx, $angleTan
            );

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

        return $edges;
    }

    /**
     * Split markers into edge markers (type ≠ Node) and node markers (type = Node).
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function partitionMarkers(Collection $markers, array $config): array
    {
        $edgeMarkers = $markers->filter(
            fn ($m) => MarkerType::fromConfig($m->marker_id, $config) !== MarkerType::Node
        );

        $nodeMarkers = $markers->filter(
            fn ($m) => MarkerType::fromConfig($m->marker_id, $config) === MarkerType::Node
        );

        return [$edgeMarkers, $nodeMarkers];
    }

    /**
     * Compute marker width in pixels from the TL → TR corner distance.
     *
     * @param  iterable  $corners  Collection of ArucoMarkerCorner models (or plain objects with position, x, y).
     */
    private function computeMarkerSize(iterable $corners): float
    {
        $corners = collect($corners);
        $tl = $corners->firstWhere('position', CornerPosition::TopLeft);
        $tr = $corners->firstWhere('position', CornerPosition::TopRight);

        if ($tl === null || $tr === null) {
            return 0.0;
        }

        return sqrt(($tr->x - $tl->x) ** 2 + ($tr->y - $tl->y) ** 2);
    }

    /**
     * Find the closest node on each side of an edge marker's x-axis.
     *
     * The allowed perpendicular offset combines a base margin (in pixels) and an
     * angle-based term: allowed_perp = baseMarginPx + angleTan × |dotProduct|.
     *
     * Returns [source, target] where source is the negative-x node and target the positive-x node.
     * Either may be null if no on-axis candidate exists on that side.
     *
     * @return array{0: mixed|null, 1: mixed|null}
     */
    private function findCandidateNodes(
        Collection $nodeMarkers,
        float $centerX,
        float $centerY,
        float $cosR,
        float $sinR,
        float $baseMarginPx,
        float $angleTan,
    ): array {
        $bestNeg = null;
        $bestPos = null;
        $bestNegDot = PHP_FLOAT_MAX;
        $bestPosDot = PHP_FLOAT_MAX;

        foreach ($nodeMarkers as $node) {
            $dx = (float) $node->center_x - $centerX;
            $dy = (float) $node->center_y - $centerY;

            $dotProduct = $dx * $cosR + $dy * $sinR;
            $perp = abs($dx * -$sinR + $dy * $cosR); // Projection of node center onto edge y-axis.

            $allowedPerp = $baseMarginPx + $angleTan * abs($dotProduct);
            if ($perp > $allowedPerp) {
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

        return [$bestNeg, $bestPos];
    }
}
