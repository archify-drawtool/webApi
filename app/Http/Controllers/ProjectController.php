<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;

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
}
