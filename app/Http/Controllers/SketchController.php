<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sketch;
use App\Services\MermaidExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SketchController extends Controller
{
    /**
     * Get all sketches owned by the logged-in user.
     */
    public function userIndex(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $sketches = Sketch::where('created_by', $request->user()->id)
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->project_id))
            ->with('creator:id,name,email')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($sketches);
    }

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
     * Create a new sketch, optionally bound to a project.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'canvas_state' => 'nullable|array',
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $sketch = Sketch::create([
            'title' => $validated['title'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'created_by' => $request->user()->id,
            'canvas_state' => $validated['canvas_state'] ?? null,
        ]);

        $sketch->load('creator:id,name,email');

        return response()->json($sketch, 201);
    }

    /**
     * Save (overwrite) the canvas state of an existing sketch.
     */
    public function updateCanvas(Request $request, Sketch $sketch): JsonResponse
    {
        abort_if($sketch->created_by !== $request->user()->id, 403);

        $request->validate([
            'canvas_state' => 'required|array',
            'canvas_state.nodes' => 'present|array',
            'canvas_state.edges' => 'present|array',
        ]);

        $sketch->update(['canvas_state' => $request->input('canvas_state')]);

        return response()->json($sketch);
    }

    public function exportMermaidFromState(Request $request, MermaidExportService $mermaid): Response
    {
        $validated = $request->validate([
            'canvas_state' => 'required|array',
            'canvas_state.nodes' => 'present|array',
            'canvas_state.edges' => 'present|array',
        ]);

        return response($mermaid->exportSketch($validated['canvas_state']), 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Rename a sketch. When bound to a project, the title must be unique within that project.
     */
    public function renameSketch(Request $request, Sketch $sketch): JsonResponse
    {
        abort_if($sketch->created_by !== $request->user()->id, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        if ($sketch->project_id !== null) {
            $duplicate = Sketch::where('project_id', $sketch->project_id)
                ->where('title', $validated['title'])
                ->where('id', '!=', $sketch->id)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'message' => 'Een schets met deze naam bestaat al binnen dit project.',
                    'errors' => [
                        'title' => ['Een schets met deze naam bestaat al binnen dit project.'],
                    ],
                ], 422);
            }
        }

        $sketch->update(['title' => $validated['title']]);
        $sketch->load('creator:id,name,email');

        return response()->json($sketch);
    }

    /**
     * Delete a sketch.
     */
    public function destroySketch(Request $request, Sketch $sketch): Response
    {
        abort_if($sketch->created_by !== $request->user()->id, 403);

        $sketch->delete();

        return response()->noContent();
    }

    /**
     * Export a sketch (nodes + edges) as a Mermaid flowchart.
     */
    public function exportMermaidSketch(Sketch $sketch, MermaidExportService $mermaid): Response
    {
        return response($mermaid->exportSketch($sketch->canvas_state ?? []), 200)
            ->header('Content-Type', 'text/plain');
    }
}
