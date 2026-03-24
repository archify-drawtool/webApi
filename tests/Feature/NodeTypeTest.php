<?php

test('node types endpoint returns 200', function () {
    $this->getJson('/api/node-types')
        ->assertStatus(200);
});

test('each node type has required fields', function () {
    $nodeTypes = $this->getJson('/api/node-types')->json();

    foreach ($nodeTypes as $nodeType) {
        expect($nodeType)
            ->toHaveKey('type')
            ->toHaveKey('name')
            ->toHaveKey('icon')
            ->toHaveKey('aruco');
    }
});

test('node type identifiers are unique', function () {
    $nodeTypes = $this->getJson('/api/node-types')->json();
    $types = array_column($nodeTypes, 'type');

    expect(array_unique($types))->toHaveCount(count($types));
});
