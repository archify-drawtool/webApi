<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PhotoController extends Controller
{
    public function __construct(private readonly PhotoService $photoService) {}

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:10240',
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id'),
            ],
        ]);

        $projectId = $request->integer('project_id');

        $ownsProject = Project::where('id', $projectId)
            ->where('created_by', $request->user()->id)
            ->exists();

        if (! $ownsProject) {
            throw ValidationException::withMessages([
                'project_id' => ['Je kunt geen foto uploaden naar een project dat niet van jou is.'],
            ]);
        }

        $path = $this->photoService->store($request->file('photo'), $projectId);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'path' => $path,
        ], 201);
    }

    public function getArucoResults(string $filename): JsonResponse
    {
        $result = $this->photoService->getDetectionResult($filename);

        if ($result === null) {
            return response()->json(
                ['message' => 'No detection result found for this filename.'],
                404
            );
        }

        return response()->json($result);
    }
}
