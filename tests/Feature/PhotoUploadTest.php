<?php

use App\Jobs\ProcessPhotoJob;
use App\Models\Photo;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('passes the uploader id to the queued photo processing job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this->actingAs($user)->post('/api/photos/upload', [
        'project_id' => $project->id,
        'photo' => UploadedFile::fake()->image('diagram.jpg', 800, 600),
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('status', 'processing');

    $photo = Photo::findOrFail($response->json('photo_id'));

    expect($photo->project_id)->toBe($project->id);

    Queue::assertPushed(
        ProcessPhotoJob::class,
        fn (ProcessPhotoJob $job) => $job->photo->is($photo)
            && $job->createdBy === $user->id,
    );
});
