<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Get all projects with creator info.
     */
    public function index(): JsonResponse
    {
        $projects = Project::with('creator:id,name,email')->get();

        return response()->json($projects);
    }

    /**
     * Create a new project for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:projects,title'],
        ]);

        $project = Project::create([
            'title'      => $validated['title'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json(
            $project->load('creator:id,name,email'),
            201
        );
    }

    /**
     * Return a single project with creator info.
     */
    public function show(Project $project): JsonResponse
    {
        return response()->json(
            $project->load('creator:id,name,email')
        );
    }
}
