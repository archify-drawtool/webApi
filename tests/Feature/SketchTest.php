<?php

use App\Models\Project;
use App\Models\Sketch;
use App\Models\User;

it('returns a sketch by id', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/sketches/{$sketch->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $sketch->id, 'title' => $sketch->title]);
});

it('returns 401 when unauthenticated on sketch by id', function () {
    $sketch = Sketch::factory()->create();

    $this->getJson("/api/sketches/{$sketch->id}")->assertUnauthorized();
});

it('returns a sketch scoped to its project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $sketch = Sketch::factory()->withNodes()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/projects/{$project->id}/sketches/{$sketch->id}")
        ->assertOk()
        ->assertJsonStructure([
            'canvas_state' => ['nodes', 'edges'],
        ]);
});

it('returns 404 when sketch does not belong to project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $otherProject = Project::factory()->create(['created_by' => $user->id]);
    $sketch = Sketch::factory()->create(['project_id' => $otherProject->id, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/projects/{$project->id}/sketches/{$sketch->id}")
        ->assertNotFound();
});

it('returns 401 when unauthenticated on project sketch', function () {
    $project = Project::factory()->create();
    $sketch = Sketch::factory()->create(['project_id' => $project->id]);

    $this->getJson("/api/projects/{$project->id}/sketches/{$sketch->id}")->assertUnauthorized();
});
