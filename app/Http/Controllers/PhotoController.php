<?php

namespace App\Http\Controllers;

use App\Enums\PhotoStatus;
use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $photo = $this->photoService->store($request->file('photo'), $projectId);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'photo_id' => $photo->id,
            'status' => $photo->status->value,
        ], 201);
    }

    public function status(Photo $photo): JsonResponse
    {
        $response = [
            'status' => $photo->status->value,
            'sketch_id' => $photo->sketch_id,
            'project_id' => $photo->project_id,
            'nodes_count' => null,
            'edges_count' => null,
            'error_message' => $photo->error_message,
        ];

        if ($photo->status === PhotoStatus::Completed && $photo->sketch_id) {
            $sketch = $photo->sketch;
            $canvasState = is_array($sketch?->canvas_state) ? $sketch->canvas_state : [];
            $response['nodes_count'] = count($canvasState['nodes'] ?? []);
            $response['edges_count'] = count($canvasState['edges'] ?? []);
        }

        return response()->json($response);
    }
}
