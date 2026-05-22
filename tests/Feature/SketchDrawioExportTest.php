<?php

use App\Models\Project;
use App\Models\Sketch;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['created_by' => $this->user->id]);
});

test('exporteert een sketch als geldig draw.io XML bestand', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'srv-1', 'type' => 'server',   'position' => ['x' => 10, 'y' => 20], 'data' => ['label' => 'API Gateway']],
                ['id' => 'db-1',  'type' => 'database', 'position' => ['x' => 200, 'y' => 20], 'data' => ['label' => 'Database']],
            ],
            'edges' => [],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->get("/api/sketches/{$sketch->id}/export/drawio");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    $body = $response->getContent();

    expect($body)
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<mxfile')
        ->toContain('id="n_srv-1"')
        ->toContain('value="API Gateway"')
        ->toContain('id="n_db-1"')
        ->toContain('value="Database"');

    $previous = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($body);
    libxml_use_internal_errors($previous);
    expect($doc)->not->toBeFalse();
});

test('exporteert een lege mxfile wanneer de sketch geen nodes heeft', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => ['nodes' => [], 'edges' => []],
    ]);

    $body = $this->actingAs($this->user)
        ->get("/api/sketches/{$sketch->id}/export/drawio")
        ->assertOk()
        ->getContent();

    expect($body)
        ->toContain('<mxfile')
        ->toContain('<mxCell id="0" />')
        ->toContain('<mxCell id="1" parent="0" />')
        ->not->toContain('vertex="1"')
        ->not->toContain('edge="1"');
});

test('exporteert een lege mxfile wanneer canvas_state null is', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => null,
    ]);

    $body = $this->actingAs($this->user)
        ->get("/api/sketches/{$sketch->id}/export/drawio")
        ->assertOk()
        ->getContent();

    expect($body)->toContain('<mxfile');
});

test('exporteert nodes en edges samen als draw.io XML', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'a', 'type' => 'server',   'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'A']],
                ['id' => 'b', 'type' => 'database', 'position' => ['x' => 200, 'y' => 0], 'data' => ['label' => 'B']],
            ],
            'edges' => [
                [
                    'id' => 'e1',
                    'source' => 'a',
                    'target' => 'b',
                    'markerEnd' => ['type' => 'arrowclosed'],
                ],
            ],
        ],
    ]);

    $body = $this->actingAs($this->user)
        ->get("/api/sketches/{$sketch->id}/export/drawio")
        ->assertOk()
        ->getContent();

    expect($body)
        ->toContain('id="e_e1"')
        ->toContain('source="n_a"')
        ->toContain('target="n_b"')
        ->toContain('endArrow=classic');
});

test('exporteert een bidirectionele edge correct als draw.io XML', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'a', 'type' => 'server',   'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'A']],
                ['id' => 'b', 'type' => 'database', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'B']],
            ],
            'edges' => [
                [
                    'id' => 'e1',
                    'source' => 'a',
                    'target' => 'b',
                    'markerStart' => ['type' => 'arrowclosed'],
                    'markerEnd' => ['type' => 'arrowclosed'],
                ],
            ],
        ],
    ]);

    $body = $this->actingAs($this->user)
        ->get("/api/sketches/{$sketch->id}/export/drawio")
        ->assertOk()
        ->getContent();

    expect($body)->toContain('endArrow=classic;startArrow=classic');
});

test('geeft 401 terug wanneer niet ingelogd', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
    ]);

    $this->getJson("/api/sketches/{$sketch->id}/export/drawio")
        ->assertUnauthorized();
});

test('exporteert canvas state via POST als draw.io XML', function () {
    $body = $this->actingAs($this->user)
        ->postJson('/api/export/drawio', [
            'canvas_state' => [
                'nodes' => [
                    ['id' => 'a', 'type' => 'server', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Server']],
                ],
                'edges' => [],
            ],
        ])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->getContent();

    expect($body)
        ->toContain('<mxfile')
        ->toContain('id="n_a"')
        ->toContain('value="Server"');
});

test('exporteert pijlen correct vanuit geposte canvas state', function () {
    $body = $this->actingAs($this->user)
        ->postJson('/api/export/drawio', [
            'canvas_state' => [
                'nodes' => [
                    ['id' => 'a', 'type' => 'server',   'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'A']],
                    ['id' => 'b', 'type' => 'database', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'B']],
                ],
                'edges' => [
                    [
                        'id' => 'e1',
                        'source' => 'a',
                        'target' => 'b',
                        'markerEnd' => ['type' => 'arrowclosed'],
                    ],
                ],
            ],
        ])
        ->assertOk()
        ->getContent();

    expect($body)
        ->toContain('source="n_a"')
        ->toContain('target="n_b"')
        ->toContain('endArrow=classic');
});

test('exportDrawioFromState geeft 401 terug wanneer niet ingelogd', function () {
    $this->postJson('/api/export/drawio', [
        'canvas_state' => ['nodes' => [], 'edges' => []],
    ])->assertUnauthorized();
});

test('exportDrawioFromState geeft 422 terug bij ontbrekende canvas_state', function () {
    $this->actingAs($this->user)
        ->postJson('/api/export/drawio', [])
        ->assertUnprocessable();
});

test('mermaid export endpoint blijft naast draw.io export werken', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'srv-1', 'type' => 'server', 'data' => ['label' => 'API']],
            ],
            'edges' => [],
        ],
    ]);

    $this->actingAs($this->user)
        ->get("/api/sketches/{$sketch->id}/export/mermaid")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});
