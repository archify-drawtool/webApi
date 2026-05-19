<?php

namespace App\Services;

use App\Enums\MarkerType;
use App\Helpers\MarkerGeometry;
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
     * When no source or target is found on the first attempt, the search is retried up
     * to `aruco.edge_retry_max_attempts` times, each time widening the angle margin by
     * `aruco.edge_retry_angle_step` degrees and the base margin factor by
     * `aruco.edge_retry_margin_step`. This recovers edges from slightly skewed markers
     * without loosening the default tolerances for well-placed ones.
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
        $retryMaxAttempts = (int) config('aruco.edge_retry_max_attempts', 3);
        $retryAngleStep = (float) config('aruco.edge_retry_angle_step', 5.0);
        $retryMarginStep = (float) config('aruco.edge_retry_margin_step', 0.25);
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

            $markerSize = MarkerGeometry::markerDimensions($edgeMarker->corners)['width'];

            [$bestNeg, $bestPos] = $this->findCandidateNodesWithRetry(
                $nodeMarkers,
                $centerX,
                $centerY,
                $cosR,
                $sinR,
                $markerSize,
                $edgeMarginFactor,
                $angleMarginDeg,
                $retryMaxAttempts,
                $retryAngleStep,
                $retryMarginStep,
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
     * Try to find candidate nodes, retrying with progressively wider tolerances when
     * either side (source or target) comes up empty.
     *
     * On attempt 0 the base config values are used unchanged. Each subsequent attempt
     * adds `$retryAngleStep` degrees to the angle margin and `$retryMarginStep` to the
     * base margin factor. The search stops as soon as both sides are populated or the
     * maximum number of attempts is exhausted.
     *
     * @return array{0: mixed|null, 1: mixed|null}
     */
    private function findCandidateNodesWithRetry(
        Collection $nodeMarkers,
        float $centerX,
        float $centerY,
        float $cosR,
        float $sinR,
        float $markerSize,
        float $baseMarginFactor,
        float $baseAngleMarginDeg,
        int $retryMaxAttempts,
        float $retryAngleStep,
        float $retryMarginStep,
    ): array {
        $bestNeg = null;
        $bestPos = null;

        for ($attempt = 0; $attempt <= $retryMaxAttempts; $attempt++) {
            $marginFactor = $baseMarginFactor + $attempt * $retryMarginStep;
            $angleMarginDeg = $baseAngleMarginDeg + $attempt * $retryAngleStep;

            $baseMarginPx = $marginFactor * $markerSize;
            $angleTan = tan(deg2rad($angleMarginDeg));

            [$bestNeg, $bestPos] = $this->findCandidateNodes(
                $nodeMarkers, $centerX, $centerY, $cosR, $sinR, $baseMarginPx, $angleTan
            );

            if ($bestNeg !== null && $bestPos !== null) {
                return [$bestNeg, $bestPos];
            }
        }

        return [$bestNeg, $bestPos];
    }

    /**
     * Split markers into edge markers (type ≠ Node) and node markers (type = Node).
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function partitionMarkers(Collection $markers, array $config): array
    {
        $grouped = $markers->groupBy(
            fn ($m) => MarkerType::fromConfig($m->marker_id, $config) === MarkerType::Node ? 'node' : 'edge'
        );

        return [$grouped->get('edge', collect()), $grouped->get('node', collect())];
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
            $nodeCenter = MarkerGeometry::markerHitboxCenter($node);
            $dx = $nodeCenter['x'] - $centerX;
            $dy = $nodeCenter['y'] - $centerY;

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
