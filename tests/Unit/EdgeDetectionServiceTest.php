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
        'aruco.edge_margin' => 0.5,       // 0.5 × marker_size → 25px with size=50
        'aruco.edge_angle_margin' => 0.0,  // no angular tolerance — keeps geometry exact
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
