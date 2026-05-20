<?php

use App\Models\Comment;
use App\Models\Project;
use App\Models\SharedLink;
use App\Models\Sketch;
use App\Models\User;

it('lists comments on a sketch with author info', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    Comment::factory(3)->create(['sketch_id' => $sketch->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/sketches/{$sketch->id}/comments")
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonStructure([
            '*' => [
                'id', 'sketch_id', 'user_id', 'parent_id', 'x', 'y', 'body', 'created_at', 'updated_at',
                'author' => ['id', 'name', 'email'],
            ],
        ]);
});

it('returns 401 when unauthenticated on comments index', function () {
    $sketch = Sketch::factory()->create();

    $this->getJson("/api/sketches/{$sketch->id}/comments")->assertUnauthorized();
});

it('lets any authenticated user list comments on any sketch', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $owner->id]);
    Comment::factory(2)->create(['sketch_id' => $sketch->id, 'user_id' => $owner->id]);

    $this->actingAs($other)
        ->getJson("/api/sketches/{$sketch->id}/comments")
        ->assertOk()
        ->assertJsonCount(2);
});

it('creates a comment on a sketch', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/sketches/{$sketch->id}/comments", [
            'x' => 120.5,
            'y' => 240.25,
            'body' => 'Deze node moet eruit',
        ])
        ->assertCreated()
        ->assertJsonPath('x', 120.5)
        ->assertJsonPath('y', 240.25)
        ->assertJsonPath('body', 'Deze node moet eruit')
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('sketch_id', $sketch->id)
        ->assertJsonPath('parent_id', null);
});

it('creates a reply comment with a valid parent_id', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    $parent = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/sketches/{$sketch->id}/comments", [
            'x' => 0,
            'y' => 0,
            'body' => 'reply',
            'parent_id' => $parent->id,
        ])
        ->assertCreated()
        ->assertJsonPath('parent_id', $parent->id)
        ->assertJsonStructure(['author' => ['id', 'name', 'email']]);
});

it('lets a different user reply to an existing comment', function () {
    $author = User::factory()->create();
    $replier = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $author->id]);
    $parent = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $author->id]);

    $this->actingAs($replier)
        ->postJson("/api/sketches/{$sketch->id}/comments", [
            'x' => 0,
            'y' => 0,
            'body' => 'mijn reply',
            'parent_id' => $parent->id,
        ])
        ->assertCreated()
        ->assertJsonPath('user_id', $replier->id)
        ->assertJsonPath('parent_id', $parent->id);
});

it('resolves a thread by deleting the top-level comment and cascading replies', function () {
    $author = User::factory()->create();
    $replier = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $author->id]);
    $parent = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $author->id]);
    $reply1 = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $replier->id, 'parent_id' => $parent->id]);
    $reply2 = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $author->id, 'parent_id' => $parent->id]);

    $this->actingAs($replier)
        ->deleteJson("/api/comments/{$parent->id}")
        ->assertNoContent();

    expect(Comment::find($parent->id))->toBeNull()
        ->and(Comment::find($reply1->id))->toBeNull()
        ->and(Comment::find($reply2->id))->toBeNull();
});

it('rejects a reply whose parent belongs to a different sketch', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    $otherSketch = Sketch::factory()->create(['created_by' => $user->id]);
    $parent = Comment::factory()->create(['sketch_id' => $otherSketch->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/sketches/{$sketch->id}/comments", [
            'x' => 0,
            'y' => 0,
            'body' => 'reply',
            'parent_id' => $parent->id,
        ])
        ->assertUnprocessable();
});

it('rejects creating a comment with missing coordinates', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/sketches/{$sketch->id}/comments", ['body' => 'oops'])
        ->assertUnprocessable();
});

it('lets any authenticated user comment on any sketch, attributing the comment to themselves', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $owner->id]);

    $this->actingAs($other)
        ->postJson("/api/sketches/{$sketch->id}/comments", ['x' => 0, 'y' => 0, 'body' => 'x'])
        ->assertCreated()
        ->assertJsonPath('user_id', $other->id);
});

it('updates the position of a comment', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    $comment = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $user->id, 'x' => 10, 'y' => 10]);

    $this->actingAs($user)
        ->patchJson("/api/comments/{$comment->id}", ['x' => 999, 'y' => 888])
        ->assertOk()
        ->assertJsonPath('x', 999)
        ->assertJsonPath('y', 888);

    expect($comment->fresh()->x)->toBe(999.0)
        ->and($comment->fresh()->y)->toBe(888.0);
});

it('updates the body of a comment', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    $comment = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $user->id, 'body' => 'oud']);

    $this->actingAs($user)
        ->patchJson("/api/comments/{$comment->id}", ['body' => 'nieuw'])
        ->assertOk()
        ->assertJsonPath('body', 'nieuw');
});

it('lets any authenticated user update or delete any comment', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $author->id]);
    $comment = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $author->id, 'body' => 'oud']);

    $this->actingAs($other)
        ->patchJson("/api/comments/{$comment->id}", ['body' => 'gewijzigd'])
        ->assertOk()
        ->assertJsonPath('body', 'gewijzigd');

    $this->actingAs($other)
        ->deleteJson("/api/comments/{$comment->id}")
        ->assertNoContent();

    expect(Comment::find($comment->id))->toBeNull();
});

it('deletes a comment', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    $comment = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/comments/{$comment->id}")
        ->assertNoContent();

    expect(Comment::find($comment->id))->toBeNull();
});

it('cascade-deletes replies when the parent comment is deleted', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    $parent = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $user->id]);
    $reply = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $user->id, 'parent_id' => $parent->id]);

    $this->actingAs($user)->deleteJson("/api/comments/{$parent->id}")->assertNoContent();

    expect(Comment::find($reply->id))->toBeNull();
});

it('removes comments when their sketch is deleted', function () {
    $user = User::factory()->create();
    $sketch = Sketch::factory()->create(['created_by' => $user->id]);
    $comment = Comment::factory()->create(['sketch_id' => $sketch->id, 'user_id' => $user->id]);

    $this->actingAs($user)->deleteJson("/api/sketches/{$sketch->id}")->assertNoContent();

    expect(Comment::find($comment->id))->toBeNull();
});

function makeShare(?string $token = null, bool $active = true): SharedLink
{
    $project = Project::factory()->create();
    $sketch = Sketch::factory()->create(['project_id' => $project->id]);

    return SharedLink::create([
        'token' => $token ?? 'token-'.uniqid(),
        'sketch_id' => $sketch->id,
        'project_id' => $project->id,
        'is_active' => $active,
    ]);
}

it('lets a guest place a comment via a public share link with guest_name', function () {
    $share = makeShare();

    $this->postJson("/api/shared/{$share->token}/comments", [
        'x' => 10,
        'y' => 20,
        'body' => 'feedback van bezoeker',
        'guest_name' => 'Bezoeker',
    ])
        ->assertCreated()
        ->assertJsonPath('user_id', null)
        ->assertJsonPath('guest_name', 'Bezoeker')
        ->assertJsonPath('sketch_id', $share->sketch_id);
});

it('requires guest_name when placing a comment via a public share link', function () {
    $share = makeShare();

    $this->postJson("/api/shared/{$share->token}/comments", [
        'x' => 0, 'y' => 0, 'body' => 'no name',
    ])->assertUnprocessable();
});

it('rejects public comments on an inactive share link', function () {
    $share = makeShare('disabled', false);

    $this->postJson("/api/shared/{$share->token}/comments", [
        'x' => 0, 'y' => 0, 'body' => 'x', 'guest_name' => 'Bezoeker',
    ])->assertNotFound();
});

it('lists every comment on a public share link including user replies, with author info', function () {
    $share = makeShare();
    $user = User::factory()->create();
    Comment::factory()->create(['sketch_id' => $share->sketch_id, 'user_id' => $user->id, 'body' => 'van user']);
    Comment::factory()->create(['sketch_id' => $share->sketch_id, 'user_id' => null, 'guest_name' => 'Anoniem', 'body' => 'van guest']);

    $this->getJson("/api/shared/{$share->token}/comments")
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonStructure([
            '*' => ['id', 'sketch_id', 'user_id', 'guest_name', 'x', 'y', 'body'],
        ]);
});

it('ignores parent_id sent by a guest and always stores a top-level comment', function () {
    $share = makeShare();
    $parent = Comment::factory()->create(['sketch_id' => $share->sketch_id, 'user_id' => null, 'guest_name' => 'Anoniem']);

    $this->postJson("/api/shared/{$share->token}/comments", [
        'x' => 0, 'y' => 0, 'body' => 'x', 'guest_name' => 'Bezoeker', 'parent_id' => $parent->id,
    ])
        ->assertCreated()
        ->assertJsonPath('parent_id', null);
});
