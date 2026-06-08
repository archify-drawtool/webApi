<?php

use App\Models\Project;
use App\Models\Sketch;
use App\Models\User;

it('returns all projects with creator info', function () {
    $user = User::factory()->create();
    Project::factory(3)->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/projects');

    $response->assertOk()
        ->assertJsonCount(3)
        ->assertJsonStructure([
            '*' => [
                'id',
                'title',
                'created_by',
                'created_at',
                'updated_at',
                'creator' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
        ]);
});

it('returns 401 when unauthenticated on projects index', function () {
    $this->getJson('/api/projects')->assertUnauthorized();
});

it('deletes a project and its sketches', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $sketches = Sketch::factory(2)->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/projects/{$project->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);

    foreach ($sketches as $sketch) {
        $this->assertDatabaseMissing('sketches', ['id' => $sketch->id]);
    }

    $this->actingAs($user)
        ->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(0);
});

it('returns 401 when unauthenticated on project delete', function () {
    $project = Project::factory()->create();

    $this->deleteJson("/api/projects/{$project->id}")->assertUnauthorized();
});

it('renames a project successfully', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
        'title' => 'New Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('title', 'New Name');
});

it('allows saving a project under its current name', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id, 'title' => 'Existing Name']);

    $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
        'title' => 'Existing Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('title', 'Existing Name');
});

it('returns 422 when renaming to a title already used by another project', function () {
    $user = User::factory()->create();
    Project::factory()->create(['title' => 'Taken Name']);
    $project = Project::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
        'title' => 'Taken Name',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

it('returns 401 when unauthenticated on project rename', function () {
    $project = Project::factory()->create();

    $this->patchJson("/api/projects/{$project->id}", ['title' => 'Anything'])->assertUnauthorized();
});
