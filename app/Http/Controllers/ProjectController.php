<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::with('creator:id,name,email')->get();

        return response()->json($projects);
    }

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

    public function rename(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', "unique:projects,title,{$project->id}"],
        ], [
            'title.unique' => 'Een project met deze naam bestaat al.',
        ]);

        $project->update(['title' => $validated['title']]);

        return response()->json($project->load('creator:id,name,email'));
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json(
            $project->load('creator:id,name,email')
        );
    }

    public function destroy(Project $project): Response
    {
        DB::transaction(function () use ($project) {
            foreach ($project->sketches as $sketch) {
                $sketch->comments()->delete();
                $sketch->delete();
            }

            $project->delete();
        });

        return response()->noContent();
    }
}
