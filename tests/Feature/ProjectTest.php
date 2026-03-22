<?php

use App\Models\Project;
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
