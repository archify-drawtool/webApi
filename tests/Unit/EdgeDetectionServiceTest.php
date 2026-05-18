<?php

use App\Enums\CornerPosition;
use App\Enums\MarkerType;
use App\Services\EdgeDetectionService;
use Illuminate\Database\Eloquent\Collection;

// Marker IDs used across tests:
//   1, 2 → node markers
//   10   → monodirectional edge marker

// Default marker size used across tests: 50px wide.
// With edge_margin=0.5 this gives a base margin of 25px.
// Angle margin is set to 0.0 in all tests for deterministic geometry.

function makeMarker(int $id, int $markerId, float $x, float $y, float $rotation, float $size = 50.0): object
{
    $tl = (object) ['position' => CornerPosition::TopLeft,  'x' => $x - $size / 2, 'y' => $y - $size / 2];
    $tr = (object) ['position' => CornerPosition::TopRight, 'x' => $x + $size / 2, 'y' => $y - $size / 2];

    return (object) [
        'id' => $id,
        'marker_id' => $markerId,
        'center_x' => $x,
        'center_y' => $y,
        'rotation' => $rotation,
        'corners' => collect([$tl, $tr]),
    ];
}

beforeEach(function () {
    $this->service = new EdgeDetectionService;

    config([
        'aruco.edge_margin' => 0.5,          // 0.5 × marker_size → 25px with size=50
        'aruco.edge_angle_margin' => 0.0,    // no angular tolerance — keeps geometry exact
        'aruco.edge_retry_max_attempts' => 0, // retries disabled — keeps existing tests deterministic
        'aruco.edge_retry_angle_step' => 5.0,
        'aruco.edge_retry_margin_step' => 0.25,
        'marker_config' => [
            1 => ['type' => 'node'],
            2 => ['type' => 'node'],
            10 => ['type' => 'monodirectional'],
        ],
    ]);
});

test('detectEdges returns an edge when two nodes are aligned with the edge marker x-axis', function () {
    // Edge marker at (100, 100) with rotation=0 — x-axis points right.
    // Node A is 50 px to the left  → dot=-50, perp=0 → source (negative side).
    // Node B is 50 px to the right → dot=+50, perp=0 → target (positive side).
    // allowed_perp = 0.5 × 50 + tan(0°) × 50 = 25px. perp=0 ≤ 25 → accepted.
    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);
    $nodeB = makeMarker(2, 2, 150.0, 100.0, 0.0);
    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 0.0);

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toHaveCount(1)
        ->and($edges[0]['source_marker']->id)->toBe(1)
        ->and($edges[0]['target_marker']->id)->toBe(2)
        ->and($edges[0]['edge_type'])->toBe(MarkerType::Monodirectional);
});

test('detectEdges returns no edge when the edge marker rotation is perpendicular to the node alignment', function () {
    // Same positions as above, but edge marker rotated 90°.
    // Its x-axis now points downward, so the left/right nodes are fully off-axis:
    //   perp = |dx × -sin(90°)| = |-50 × -1| = 50 > allowed_perp(25) → both nodes discarded.
    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);
    $nodeB = makeMarker(2, 2, 150.0, 100.0, 0.0);
    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 90.0);

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toBeEmpty();
});

test('detectEdges rejects a node whose perpendicular offset exceeds the base margin', function () {
    // Edge marker at (100, 100) rotation=0, size=50 → base_margin=25px.
    // Node A is perfectly on-axis (perp=0). Node B is 30px off-axis (perp=30 > 25) → rejected.
    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);
    $nodeB = makeMarker(2, 2, 150.0, 130.0, 0.0); // 30px above axis

    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 0.0);

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toBeEmpty(); // No target → no edge.
});

test('detectEdges accepts a distant node via angle tolerance', function () {
    // Edge marker at (100, 100) rotation=0, size=50 → base_margin=25px.
    // angle_margin = 45° → tan(45°)=1.0, so allowed_perp = 25 + 1.0 × |dot|.
    // Node B is at (600, 130): dot=500, perp=30. allowed_perp = 25 + 500 = 525 → accepted.
    config(['aruco.edge_angle_margin' => 45.0]);

    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);
    $nodeB = makeMarker(2, 2, 600.0, 130.0, 0.0); // 30px off-axis, 500px along axis

    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 0.0);

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toHaveCount(1)
        ->and($edges[0]['source_marker']->id)->toBe(1)
        ->and($edges[0]['target_marker']->id)->toBe(2);
});

// ---------------------------------------------------------------------------
// Iterative retry tests
// ---------------------------------------------------------------------------

test('detectEdges finds an edge on retry when the marker is slightly skewed', function () {
    // Edge marker is tilted 10° off from the node axis.
    // With angle_margin=0° and retry disabled this would fail.
    // With 1 retry at +5° angle_step the allowed cone becomes 5°, which is still too tight.
    // With 2 retries the cone reaches 10° → tan(10°) ≈ 0.176.
    // NodeA is 50px to the left (dot=-50, perp=0) → always accepted.
    // NodeB is 50px to the right along the true axis at (150, 100), but the marker
    // is rotated 10°. From the marker's rotated frame:
    //   rotation = 10° → cosR ≈ 0.985, sinR ≈ 0.174
    //   dx=50, dy=0: dot = 50×0.985 ≈ 49.2, perp = |50×-0.174| ≈ 8.7
    //   attempt 0 (angle=0°):  allowed = 25 + 0   × 49.2 = 25.0  → 8.7 ≤ 25 → accepted ✓
    // So a 10° skew with base_margin=25px actually passes on attempt 0.
    // Test a tighter margin (edge_margin=0.1 → base=5px) so retry is needed:
    //   attempt 0 (angle=0°):  allowed = 5 + 0    × 49.2 =  5.0  → 8.7 > 5  → rejected
    //   attempt 1 (angle=5°):  allowed = 5 + 0.087× 49.2 =  9.3  → 8.7 ≤ 9.3 → accepted ✓
    config([
        'aruco.edge_margin' => 0.1,           // tight: base_margin = 0.1 × 50 = 5px
        'aruco.edge_angle_margin' => 0.0,
        'aruco.edge_retry_max_attempts' => 3,
        'aruco.edge_retry_angle_step' => 5.0,
        'aruco.edge_retry_margin_step' => 0.0, // hold margin constant so only angle widens
    ]);

    // Edge marker rotated 10° clockwise — slightly skewed relative to the node axis.
    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 10.0);
    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);  // on the true horizontal axis
    $nodeB = makeMarker(2, 2, 150.0, 100.0, 0.0); // on the true horizontal axis

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toHaveCount(1)
        ->and($edges[0]['source_marker']->id)->toBe(1)
        ->and($edges[0]['target_marker']->id)->toBe(2);
});

test('detectEdges still returns no edge when skew exceeds all retry attempts', function () {
    // Edge marker rotated 90°, nodes are horizontal — perpendicular distance is too large
    // for any reasonable retry. With 3 retries at +5° per step the cone only reaches 15°,
    // which is nowhere near enough to bridge a 90° skew.
    config([
        'aruco.edge_margin' => 0.5,
        'aruco.edge_angle_margin' => 0.0,
        'aruco.edge_retry_max_attempts' => 3,
        'aruco.edge_retry_angle_step' => 5.0,
        'aruco.edge_retry_margin_step' => 0.25,
    ]);

    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);
    $nodeB = makeMarker(2, 2, 150.0, 100.0, 0.0);
    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 90.0); // pointing straight down

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toBeEmpty();
});

test('detectEdges with retry_max_attempts=0 behaves identically to original logic', function () {
    // Sanity check: setting max_attempts=0 means only the initial attempt runs,
    // which is the same as the pre-retry behaviour.
    config([
        'aruco.edge_margin' => 0.5,
        'aruco.edge_angle_margin' => 0.0,
        'aruco.edge_retry_max_attempts' => 0,
        'aruco.edge_retry_angle_step' => 5.0,
        'aruco.edge_retry_margin_step' => 0.25,
    ]);

    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);
    $nodeB = makeMarker(2, 2, 150.0, 100.0, 0.0);
    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 0.0);

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toHaveCount(1)
        ->and($edges[0]['source_marker']->id)->toBe(1)
        ->and($edges[0]['target_marker']->id)->toBe(2);
});

test('detectEdges finds an edge via margin widening when angle is correct but marker is far off-axis', function () {
    // Edge marker at (100, 100) rotation=0°, size=50 → initial base_margin = 0.1×50 = 5px.
    // NodeA is perfectly on-axis (perp=0). NodeB is 20px off-axis — too far for attempt 0.
    // retry_margin_step=0.5 → on attempt 1: margin_factor = 0.1 + 0.5 = 0.6 → base=30px > 20px ✓
    config([
        'aruco.edge_margin' => 0.1,            // tight initial margin
        'aruco.edge_angle_margin' => 0.0,
        'aruco.edge_retry_max_attempts' => 3,
        'aruco.edge_retry_angle_step' => 0.0,  // hold angle constant so only margin widens
        'aruco.edge_retry_margin_step' => 0.5,
    ]);

    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);
    $nodeB = makeMarker(2, 2, 150.0, 120.0, 0.0); // 20px off-axis

    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 0.0);

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toHaveCount(1)
        ->and($edges[0]['source_marker']->id)->toBe(1)
        ->and($edges[0]['target_marker']->id)->toBe(2);
});
