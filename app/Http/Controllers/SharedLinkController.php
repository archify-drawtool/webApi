<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SharedLink;
use App\Models\Sketch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharedLinkController extends Controller
{
    public function status(Request $request, Project $project, Sketch $sketch): JsonResponse
    {
        abort_if($sketch->project_id !== $project->id, 404);

        $sharedLink = SharedLink::where('sketch_id', $sketch->id)->first();

        if (! $sharedLink) {
            return response()->json([
                'is_active' => false,
                'token' => null,
                'public_url' => null,
            ]);
        }

        return response()->json([
            'is_active' => $sharedLink->is_active,
            'token' => $sharedLink->token,
            'public_url' => url('/api/shared/'.$sharedLink->token),
        ]);
    }

    public function toggle(Request $request, Project $project, Sketch $sketch): JsonResponse
    {
        abort_if($sketch->project_id !== $project->id, 404);

        $sharedLink = SharedLink::where('sketch_id', $sketch->id)->first();

        if (! $sharedLink) {
            $sharedLink = SharedLink::create([
                'token' => Str::random(64),
                'sketch_id' => $sketch->id,
                'project_id' => $project->id,
                'is_active' => true,
            ]);
        } else {
            $sharedLink->is_active = ! $sharedLink->is_active;
            $sharedLink->save();
        }

        return response()->json([
            'is_active' => $sharedLink->is_active,
            'token' => $sharedLink->token,
            'public_url' => url('/api/shared/'.$sharedLink->token),
        ]);
    }

    public function nodesTypes(): JsonResponse
    {
        $types = array_map(
            fn ($type) => ['type' => $type['type'], 'icon' => $type['icon']],
            config('node_types')
        );

        return response()->json($types);
    }

    public function show(string $token): JsonResponse
    {
        $sharedLink = SharedLink::where('token', $token)->first();

        abort_if(! $sharedLink || ! $sharedLink->is_active, 404, 'Deze link is niet meer geldig.');

        $sharedLink->load('sketch.project');

        return response()->json([
            'title' => $sharedLink->sketch->title,
            'project_title' => $sharedLink->sketch->project->title,
            'canvas_state' => $sharedLink->sketch->canvas_state,
            'has_photo' => $sharedLink->sketch->photo()->whereNotNull('path')->exists(),
        ]);
    }

    public function comments(string $token): JsonResponse
    {
        $sketch = $this->resolveSketch($token);

        $comments = $sketch->comments()
            ->with('author:id,name,email')
            ->orderBy('created_at')
            ->get();

        return response()->json($comments);
    }

    public function storeComment(Request $request, string $token): JsonResponse
    {
        $sketch = $this->resolveSketch($token);

        $validated = $request->validate([
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'body' => ['nullable', 'string', 'max:5000'],
            'guest_name' => ['required', 'string', 'max:80'],
        ]);

        $comment = $sketch->comments()->create([
            'user_id' => null,
            'guest_name' => trim($validated['guest_name']),
            'parent_id' => null,
            'x' => $validated['x'],
            'y' => $validated['y'],
            'body' => $validated['body'] ?? '',
        ]);

        return response()->json($comment, 201);
    }

    private function resolveSketch(string $token): Sketch
    {
        $sharedLink = SharedLink::where('token', $token)->first();

        abort_if(! $sharedLink || ! $sharedLink->is_active, 404, 'Deze link is niet meer geldig.');

        return $sharedLink->sketch;
    }

    public function showPhoto(string $token): StreamedResponse
    {
        $sharedLink = SharedLink::where('token', $token)->first();

        abort_if(! $sharedLink || ! $sharedLink->is_active, 404, 'Deze link is niet meer geldig.');

        $photo = $sharedLink->sketch->photo()->whereNotNull('path')->first();

        abort_if($photo === null, 404);
        abort_unless(Storage::disk('s3')->exists($photo->path), 404);

        return Storage::disk('s3')->response($photo->path);
    }
}
