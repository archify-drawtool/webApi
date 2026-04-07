<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sketch;
use Illuminate\Http\JsonResponse;

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
}
