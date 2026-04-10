<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sketch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SketchController extends Controller
{
    /**
     * Get a single sketch by ID.
     */
    public function show(Sketch $sketch): JsonResponse
    {
        $sketch->load('creator:id,name,email');

        return response()->json($sketch);
    }

    /**
     * Get all sketches for a given project with creator info.
     */
    public function index(Project $project): JsonResponse
    {
        $sketches = $project->sketches()->with('creator:id,name,email')->get();

        return response()->json($sketches);
    }

    /**
     * Get a single sketch scoped to a project.
     */
    public function showForProject(Project $project, Sketch $sketch): JsonResponse
    {
        abort_if($sketch->project_id !== $project->id, 404);

        $sketch->load('creator:id,name,email');

        return response()->json($sketch);
    }

    /**
     * Create a new sketch for a project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'canvas_state' => 'nullable|array',
        ]);

        $sketch = $project->sketches()->create([
            'title' => $validated['title'],
            'created_by' => $request->user()->id,
            'canvas_state' => $validated['canvas_state'] ?? null,
        ]);

        $sketch->load('creator:id,name,email');

        return response()->json($sketch, 201);
    }

    /**
     * Save (overwrite) the canvas state of an existing sketch.
     */
    public function update(Request $request, Project $project, Sketch $sketch): JsonResponse
    {
        abort_if($sketch->project_id !== $project->id, 404);

        $validated = $request->validate([
            'canvas_state' => 'required|array',
            'canvas_state.nodes' => 'required|array',
            'canvas_state.edges' => 'required|array',
        ]);

        $sketch->update(['canvas_state' => $validated['canvas_state']]);

        return response()->json($sketch);
    }
}
