<?php

use App\Models\Project;
use App\Models\Sketch;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['created_by' => $this->user->id]);
});

test('exporteert een sketch met nodes als geldige Mermaid flowchart', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'srv-1', 'type' => 'server', 'data' => ['label' => 'API Gateway']],
                ['id' => 'db-1',  'type' => 'database', 'data' => ['label' => 'Database']],
            ],
            'edges' => [],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->get("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $body = $response->getContent();
    expect($body)->toContain('flowchart TD')
        ->and($body)->toContain('srv-1[["API Gateway"]]')
        ->and($body)->toContain('db-1[("Database")]');
});

test('exporteert een lege flowchart wanneer de sketch geen nodes heeft', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => ['nodes' => [], 'edges' => []],
    ]);

    $response = $this->actingAs($this->user)
        ->get("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid");

    $response->assertOk();
    expect($response->getContent())->toBe('flowchart TD');
});

test('exporteert een lege flowchart wanneer canvas_state null is', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid");

    $response->assertOk();
    expect($response->getContent())->toBe('flowchart TD');
});

test('slaat nodes zonder id over en exporteert de rest correct', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'srv-1', 'type' => 'server', 'data' => ['label' => 'Geldig']],
                ['type' => 'server', 'data' => ['label' => 'Geen ID']],
            ],
            'edges' => [],
        ],
    ]);

    $body = $this->actingAs($this->user)
        ->get("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid")
        ->assertOk()
        ->getContent();

    expect($body)
        ->toContain('srv-1[["Geldig"]]')
        ->not->toContain('Geen ID');
});

test('exporteert nodes en edges samen als geldige Mermaid flowchart', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'srv-1', 'type' => 'server',   'data' => ['label' => 'API']],
                ['id' => 'db-1',  'type' => 'database',  'data' => ['label' => 'DB']],
            ],
            'edges' => [
                [
                    'id' => 'e1',
                    'source' => 'srv-1',
                    'target' => 'db-1',
                    'markerEnd' => ['type' => 'arrowclosed'],
                ],
            ],
        ],
    ]);

    $body = $this->actingAs($this->user)
        ->get("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid")
        ->assertOk()
        ->getContent();

    expect($body)
        ->toContain('srv-1[["API"]]')
        ->toContain('db-1[("DB")]')
        ->toContain('srv-1 --> db-1');
});

test('exporteert een edge met label correct', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'a', 'type' => 'server',   'data' => ['label' => 'A']],
                ['id' => 'b', 'type' => 'database',  'data' => ['label' => 'B']],
            ],
            'edges' => [
                [
                    'id' => 'e1',
                    'source' => 'a',
                    'target' => 'b',
                    'markerEnd' => ['type' => 'arrowclosed'],
                    'label' => 'stuurt data',
                ],
            ],
        ],
    ]);

    $body = $this->actingAs($this->user)
        ->get("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid")
        ->assertOk()
        ->getContent();

    expect($body)->toContain('a -->|"stuurt data"| b');
});

test('exporteert een bidirectionele edge correct', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'canvas_state' => [
            'nodes' => [
                ['id' => 'a', 'type' => 'server',   'data' => ['label' => 'A']],
                ['id' => 'b', 'type' => 'database',  'data' => ['label' => 'B']],
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
        ->get("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid")
        ->assertOk()
        ->getContent();

    expect($body)->toContain('a <--> b');
});

test('geeft 401 terug wanneer niet ingelogd', function () {
    $sketch = Sketch::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
    ]);

    $this->getJson("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid")
        ->assertUnauthorized();
});

test('geeft 404 terug wanneer de sketch niet bij het project hoort', function () {
    $otherProject = Project::factory()->create(['created_by' => $this->user->id]);
    $sketch = Sketch::factory()->create([
        'project_id' => $otherProject->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get("/api/projects/{$this->project->id}/sketches/{$sketch->id}/export/mermaid")
        ->assertNotFound();
});
