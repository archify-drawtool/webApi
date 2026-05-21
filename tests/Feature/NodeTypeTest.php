<?php

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('node types endpoint returns 200', function () {
    $this->getJson('/api/node-types')
        ->assertStatus(200);
});

test('each node type has required fields with correct data types', function () {
    $nodeTypes = $this->getJson('/api/node-types')->json();

    foreach ($nodeTypes as $nodeType) {
        expect($nodeType)->toHaveKeys(['type', 'name', 'icon', 'aruco', 'mermaid_shape'])
            ->and($nodeType['type'])->toBeString()
            ->and($nodeType['name'])->toBeString()
            ->and($nodeType['icon'])->toBeString()
            ->and($nodeType['mermaid_shape'])->toBeString();

        // aruco mag null zijn voor node-types die niet via ArUco gescand worden (bijv. note)
        if ($nodeType['aruco'] !== null) {
            expect($nodeType['aruco'])->toBeInt();
        }
    }
});

test('each node type has a valid mermaid_shape value', function () {
    $validShapes = ['rectangle', 'subroutine', 'cylinder', 'hexagon', 'circle', 'rounded', 'note'];
    $nodeTypes = $this->getJson('/api/node-types')->json();

    foreach ($nodeTypes as $nodeType) {
        expect($validShapes)->toContain($nodeType['mermaid_shape']);
    }
});

test('note node type is aanwezig in de lijst', function () {
    $nodeTypes = $this->getJson('/api/node-types')->json();
    $types = array_column($nodeTypes, 'type');

    expect($types)->toContain('note');
});

test('note node type heeft geen aruco koppeling', function () {
    $nodeTypes = $this->getJson('/api/node-types')->json();
    $noteType = collect($nodeTypes)->firstWhere('type', 'note');

    expect($noteType)->not->toBeNull()
        ->and($noteType['aruco'])->toBeNull()
        ->and($noteType['mermaid_shape'])->toBe('note');
});

test('node type identifiers are unique', function () {
    $nodeTypes = $this->getJson('/api/node-types')->json();
    $types = array_column($nodeTypes, 'type');

    expect(array_unique($types))->toHaveCount(count($types));
});
