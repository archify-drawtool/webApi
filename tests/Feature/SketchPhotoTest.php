<?php

use App\Enums\PhotoStatus;
use App\Models\Photo;
use App\Models\Project;
use App\Models\SharedLink;
use App\Models\Sketch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function makePhoto(Sketch $sketch, ?string $path = 'photos/test.jpg'): Photo
{
    if ($path !== null) {
        Storage::disk('local')->put($path, 'fake-jpeg-bytes');
    }

    return Photo::create([
        'project_id' => $sketch->project_id,
        'filename' => 'test.jpg',
        'path' => $path,
        'status' => PhotoStatus::Completed,
        'sketch_id' => $sketch->id,
    ]);
}

it('exposes has_photo=true on the show response when a photo is linked', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    makePhoto($sketch);

    $this->actingAs($user)
        ->getJson("/api/sketches/{$sketch->id}")
        ->assertOk()
        ->assertJsonFragment(['has_photo' => true]);
});

it('exposes has_photo=false on the show response when no photo is linked', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/sketches/{$sketch->id}")
        ->assertOk()
        ->assertJsonFragment(['has_photo' => false]);
});

it('streams the original photo for the auth endpoint', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    makePhoto($sketch);

    $response = $this->actingAs($user)
        ->get("/api/sketches/{$sketch->id}/photo");

    $response->assertOk();
    expect($response->streamedContent())->toBe('fake-jpeg-bytes');
});

it('returns 404 from the auth photo endpoint when no photo is linked', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get("/api/sketches/{$sketch->id}/photo")
        ->assertNotFound();
});

it('returns 404 from the auth photo endpoint when the file is missing on disk', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    makePhoto($sketch, 'photos/missing.jpg');
    Storage::disk('local')->delete('photos/missing.jpg');

    $this->actingAs($user)
        ->get("/api/sketches/{$sketch->id}/photo")
        ->assertNotFound();
});

it('requires authentication on the auth photo endpoint', function () {
    $sketch = Sketch::factory()->create();

    $this->getJson("/api/sketches/{$sketch->id}/photo")->assertUnauthorized();
});

it('exposes has_photo on the shared show response', function () {
    $project = Project::factory()->create();
    $sketch = Sketch::factory()->create(['project_id' => $project->id]);
    $sharedLink = SharedLink::create([
        'token' => 'abc123',
        'sketch_id' => $sketch->id,
        'project_id' => $project->id,
        'is_active' => true,
    ]);
    makePhoto($sketch);

    $this->getJson("/api/shared/{$sharedLink->token}")
        ->assertOk()
        ->assertJsonFragment(['has_photo' => true]);
});

it('streams the original photo via the public shared endpoint', function () {
    $project = Project::factory()->create();
    $sketch = Sketch::factory()->create(['project_id' => $project->id]);
    $sharedLink = SharedLink::create([
        'token' => 'public-token',
        'sketch_id' => $sketch->id,
        'project_id' => $project->id,
        'is_active' => true,
    ]);
    makePhoto($sketch);

    $response = $this->get("/api/shared/{$sharedLink->token}/photo");

    $response->assertOk();
    expect($response->streamedContent())->toBe('fake-jpeg-bytes');
});

it('returns 404 from the public photo endpoint when the shared link is inactive', function () {
    $project = Project::factory()->create();
    $sketch = Sketch::factory()->create(['project_id' => $project->id]);
    $sharedLink = SharedLink::create([
        'token' => 'inactive-token',
        'sketch_id' => $sketch->id,
        'project_id' => $project->id,
        'is_active' => false,
    ]);
    makePhoto($sketch);

    $this->get("/api/shared/{$sharedLink->token}/photo")->assertNotFound();
});

it('returns 404 from the public photo endpoint when no photo is linked', function () {
    $project = Project::factory()->create();
    $sketch = Sketch::factory()->create(['project_id' => $project->id]);
    $sharedLink = SharedLink::create([
        'token' => 'nophoto-token',
        'sketch_id' => $sketch->id,
        'project_id' => $project->id,
        'is_active' => true,
    ]);

    $this->get("/api/shared/{$sharedLink->token}/photo")->assertNotFound();
});
