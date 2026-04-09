<?php

use App\Enums\MarkerType;
use App\Services\EdgeDetectionService;
use Illuminate\Database\Eloquent\Collection;

// Marker IDs used across tests:
//   1, 2 → node markers
//   10   → monodirectional edge marker

function makeMarker(int $id, int $markerId, float $x, float $y, float $rotation): object
{
    return (object) [
        'id' => $id,
        'marker_id' => $markerId,
        'center_x' => $x,
        'center_y' => $y,
        'rotation' => $rotation,
    ];
}

beforeEach(function () {
    $this->service = new EdgeDetectionService;

    config([
        'aruco.edge_margin' => 20,
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
    //   perp = |dx * -sin(90°)| = |-50 * -1| = 50 > margin(20) → both nodes discarded.
    $nodeA = makeMarker(1, 1, 50.0, 100.0, 0.0);
    $nodeB = makeMarker(2, 2, 150.0, 100.0, 0.0);
    $edgeMarker = makeMarker(3, 10, 100.0, 100.0, 90.0);

    $edges = $this->service->detectEdges(Collection::make([$nodeA, $nodeB, $edgeMarker]));

    expect($edges)->toBeEmpty();
});
