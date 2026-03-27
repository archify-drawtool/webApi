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
        expect($nodeType)->toHaveKeys(['type', 'name', 'icon', 'aruco'])
            ->and($nodeType['type'])->toBeString()
            ->and($nodeType['name'])->toBeString()
            ->and($nodeType['icon'])->toBeString()
            ->and($nodeType['aruco'])->toBeInt();
    }
});

test('node type identifiers are unique', function () {
    $nodeTypes = $this->getJson('/api/node-types')->json();
    $types = array_column($nodeTypes, 'type');

    expect(array_unique($types))->toHaveCount(count($types));
});
