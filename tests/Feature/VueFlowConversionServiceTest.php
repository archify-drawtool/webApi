<?php

use App\Enums\MarkerType;
use App\Models\ArucoMarker;
use App\Models\DetectedEdge;
use App\Models\DetectionResult;
use App\Models\Project;
use App\Models\User;
use App\Services\VueFlowConversionService;

test('converts 4 nodes and 3 edges of all types into a sketch', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $this->actingAs($user);

    $result = DetectionResult::create([
        'filename' => 'test.jpg',
        'image_path' => '/tmp/test.jpg',
        'detection_failed' => false,
        'detected_at' => now(),
    ]);

    // 4 node markers — IDs 1–4 map to rectangle, server, database, application in node_types config
    $node1 = ArucoMarker::create(['detection_result_id' => $result->id, 'marker_id' => 1, 'center_x' => 100, 'center_y' => 100, 'rotation' => 0, 'ocr_text' => 'Node A']);
    $node2 = ArucoMarker::create(['detection_result_id' => $result->id, 'marker_id' => 2, 'center_x' => 500, 'center_y' => 100, 'rotation' => 0, 'ocr_text' => 'Node B']);
    $node3 = ArucoMarker::create(['detection_result_id' => $result->id, 'marker_id' => 3, 'center_x' => 500, 'center_y' => 400, 'rotation' => 0, 'ocr_text' => 'Node C']);
    $node4 = ArucoMarker::create(['detection_result_id' => $result->id, 'marker_id' => 4, 'center_x' => 100, 'center_y' => 400, 'rotation' => 0, 'ocr_text' => 'Node D']);

    // Edge markers — IDs 21/22/23 map to directionless/monodirectional/bidirectional in marker_config
    $edgeMarker1 = ArucoMarker::create(['detection_result_id' => $result->id, 'marker_id' => 21, 'center_x' => 300, 'center_y' => 100, 'rotation' => 0, 'ocr_text' => 'links A–B']);
    $edgeMarker2 = ArucoMarker::create(['detection_result_id' => $result->id, 'marker_id' => 22, 'center_x' => 500, 'center_y' => 250, 'rotation' => 0, 'ocr_text' => 'links B–C']);
    $edgeMarker3 = ArucoMarker::create(['detection_result_id' => $result->id, 'marker_id' => 23, 'center_x' => 300, 'center_y' => 400, 'rotation' => 0, 'ocr_text' => 'links C–D']);

    DetectedEdge::create([
        'detection_result_id' => $result->id,
        'edge_marker_id' => $edgeMarker1->id,
        'source_marker_id' => $node1->id,
        'target_marker_id' => $node2->id,
        'edge_type' => MarkerType::Directionless,
    ]);
    DetectedEdge::create([
        'detection_result_id' => $result->id,
        'edge_marker_id' => $edgeMarker2->id,
        'source_marker_id' => $node2->id,
        'target_marker_id' => $node3->id,
        'edge_type' => MarkerType::Monodirectional,
    ]);
    DetectedEdge::create([
        'detection_result_id' => $result->id,
        'edge_marker_id' => $edgeMarker3->id,
        'source_marker_id' => $node3->id,
        'target_marker_id' => $node4->id,
        'edge_type' => MarkerType::Bidirectional,
    ]);

    $sketch = (new VueFlowConversionService)->convert($result, $project->id);

    $nodes = $sketch->canvas_state['nodes'];
    $edges = $sketch->canvas_state['edges'];

    expect($nodes)->toHaveCount(4);
    expect($edges)->toHaveCount(3);

    // Node types resolved from node_types config
    $types = collect($nodes)->pluck('type')->sort()->values()->all();
    expect($types)->toBe(['application', 'database', 'rectangle', 'server']);

    // OCR text is used as the label
    $labels = collect($nodes)->pluck('data.label')->sort()->values()->all();
    expect($labels)->toBe(['Node A', 'Node B', 'Node C', 'Node D']);

    // Directionless: no arrow markers
    $directionless = collect($edges)->firstWhere('data.edgeType', MarkerType::Directionless->value);
    expect($directionless)->not->toHaveKey('markerStart')
        ->and($directionless)->not->toHaveKey('markerEnd');

    // Monodirectional: arrowhead only at target
    $monodirectional = collect($edges)->firstWhere('data.edgeType', MarkerType::Monodirectional->value);
    expect($monodirectional)->not->toHaveKey('markerStart')
        ->and($monodirectional['markerEnd'])->toBe(['type' => 'arrowclosed']);

    // Bidirectional: arrowheads at both ends
    $bidirectional = collect($edges)->firstWhere('data.edgeType', MarkerType::Bidirectional->value);
    expect($bidirectional['markerStart'])->toBe(['type' => 'arrowclosed'])
        ->and($bidirectional['markerEnd'])->toBe(['type' => 'arrowclosed']);

    // All positions are within the normalized canvas bounds (100–1300 × 100–700)
    foreach ($nodes as $node) {
        expect($node['position']['x'])
            ->toBeGreaterThanOrEqual(100.0)
            ->toBeLessThanOrEqual(1300.0);
        expect($node['position']['y'])
            ->toBeGreaterThanOrEqual(100.0)
            ->toBeLessThanOrEqual(700.0);
    }
});
