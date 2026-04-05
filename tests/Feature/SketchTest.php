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

it('returns all sketches for a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    Sketch::factory(3)->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/projects/{$project->id}/sketches")
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonStructure([
            '*' => [
                'id',
                'title',
                'project_id',
                'created_by',
                'created_at',
                'updated_at',
                'creator' => ['id', 'name', 'email'],
            ],
        ]);
});

it('returns only sketches belonging to the given project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $otherProject = Project::factory()->create(['created_by' => $user->id]);

    Sketch::factory(2)->create(['project_id' => $project->id, 'created_by' => $user->id]);
    Sketch::factory(5)->create(['project_id' => $otherProject->id, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/projects/{$project->id}/sketches")
        ->assertOk()
        ->assertJsonCount(2);
});

it('returns an empty array when project has no sketches', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/projects/{$project->id}/sketches")
        ->assertOk()
        ->assertExactJson([]);
});

it('returns 401 when unauthenticated on project sketches index', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/projects/{$project->id}/sketches")->assertUnauthorized();
});
