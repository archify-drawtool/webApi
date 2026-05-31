<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'title' => [
                'required', 'string', 'max:255',
                function ($_, $value, $fail) {
                    if (Project::query()->where(DB::raw('LOWER(title)'), strtolower($value))->exists()) {
                        $fail('Een project met deze naam bestaat al.');
                    }
                },
            ],
        ]);

        $project = Project::create([
            'title' => $validated['title'],
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
