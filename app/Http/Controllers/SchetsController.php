<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Schets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchetsController extends Controller
{
    /**
     * Get all schetsen for a given project with creator info.
     */
    public function index(Project $project): JsonResponse
    {
        $schetsen = $project->schetsen()->with('creator:id,name,email')->get();

        return response()->json($schetsen);
    }

    /**
     * Create a new schets for the given project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('schetsen', 'title')->where('project_id', $project->id),
            ],
        ]);

        $schets = Schets::create([
            'title'      => $validated['title'],
            'project_id' => $project->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(
            $schets->load('creator:id,name,email'),
            201
        );
    }

    /**
     * Return a single schets with creator info.
     */
    public function show(Project $project, Schets $schets): JsonResponse
    {
        return response()->json(
            $schets->load('creator:id,name,email')
        );
    }
}
