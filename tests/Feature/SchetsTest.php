<?php

use App\Models\Project;
use App\Models\Schets;
use App\Models\User;

it('returns all schetsen for a project with creator info', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    Schets::factory(3)->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/schetsen");

    $response->assertOk()
        ->assertJsonCount(3)
        ->assertJsonStructure([
            '*' => [
                'id',
                'title',
                'project_id',
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

it('returns 401 when unauthenticated on schetsen index', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/projects/{$project->id}/schetsen")->assertUnauthorized();
});

it('creates a schets for a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);

    $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/schetsen", [
        'title' => 'Nieuwe schets',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'id',
            'title',
            'project_id',
            'created_by',
            'created_at',
            'updated_at',
            'creator' => [
                'id',
                'name',
                'email',
            ],
        ])
        ->assertJsonFragment([
            'title' => 'Nieuwe schets',
            'project_id' => $project->id,
            'created_by' => $user->id,
        ]);

    $this->assertDatabaseHas('schetsen', [
        'title' => 'Nieuwe schets',
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);
});

it('rejects a duplicate title within the same project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    Schets::factory()->create(['title' => 'Bestaande schets', 'project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/schetsen", [
        'title' => 'Bestaande schets',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('allows the same title in a different project', function () {
    $user = User::factory()->create();
    $project1 = Project::factory()->create(['created_by' => $user->id]);
    $project2 = Project::factory()->create(['created_by' => $user->id]);
    Schets::factory()->create(['title' => 'Gedeelde naam', 'project_id' => $project1->id]);

    $response = $this->actingAs($user)->postJson("/api/projects/{$project2->id}/schetsen", [
        'title' => 'Gedeelde naam',
    ]);

    $response->assertCreated();
});

it('returns a single schets with creator info', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $schets = Schets::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/schetsen/{$schets->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'title',
            'project_id',
            'created_by',
            'created_at',
            'updated_at',
            'creator' => [
                'id',
                'name',
                'email',
            ],
        ])
        ->assertJsonFragment(['id' => $schets->id]);
});

it('returns 401 when unauthenticated on schets show', function () {
    $project = Project::factory()->create();
    $schets = Schets::factory()->create(['project_id' => $project->id]);

    $this->getJson("/api/projects/{$project->id}/schetsen/{$schets->id}")->assertUnauthorized();
});
