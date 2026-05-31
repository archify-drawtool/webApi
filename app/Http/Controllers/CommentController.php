<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Sketch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function index(Sketch $sketch): JsonResponse
    {
        $comments = $sketch->comments()
            ->with('author:id,name,email')
            ->orderBy('created_at')
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request, Sketch $sketch): JsonResponse
    {
        $validated = $request->validate([
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'body' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        if (! empty($validated['parent_id'])) {
            $parent = Comment::find($validated['parent_id']);
            abort_if($parent === null || $parent->sketch_id !== $sketch->id, 422);
        }

        $comment = $sketch->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'x' => $validated['x'],
            'y' => $validated['y'],
            'body' => $validated['body'] ?? '',
        ]);

        $comment->load('author:id,name,email');

        return response()->json($comment, 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate([
            'x' => ['sometimes', 'numeric'],
            'y' => ['sometimes', 'numeric'],
            'body' => ['sometimes', 'string', 'max:5000'],
        ]);

        if (array_key_exists('body', $validated) && $comment->user_id !== $request->user()->id) {
            abort(403);
        }

        $comment->update($validated);
        $comment->load('author:id,name,email');

        return response()->json($comment);
    }

    public function destroy(Request $request, Comment $comment): Response
    {
        if ($comment->parent_id !== null && $comment->user_id !== $request->user()->id) {
            abort(403);
        }

        $comment->delete();

        return response()->noContent();
    }
}
