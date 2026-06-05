<?php

namespace App\Http\Controllers;

use App\Models\PhotoPreview;
use App\Services\PhotoPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PhotoPreviewController extends Controller
{
    public function __construct(
        private readonly PhotoPreviewService $previewService,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:10240',
        ]);

        $preview = $this->previewService->startPreview(
            $request->file('photo'),
            $request->user()->id,
        );

        return response()->json([
            'preview_id' => $preview->id,
            'status' => $preview->status->value,
        ], 201);
    }

    public function status(Request $request, PhotoPreview $preview): JsonResponse
    {
        abort_if($preview->user_id !== $request->user()->id, 403);

        return response()->json([
            'status' => $preview->status->value,
            'nodes_count' => $preview->nodes_count,
            'edges_count' => $preview->edges_count,
        ]);
    }

    public function commit(Request $request, PhotoPreview $preview): JsonResponse
    {
        abort_if($preview->user_id !== $request->user()->id, 403);

        $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'rotation' => ['nullable', 'integer'],
        ]);

        $projectId = $request->filled('project_id') ? $request->integer('project_id') : null;

        $photo = $this->previewService->commit($preview, $projectId, $request->user()->id);

        return response()->json([
            'photo_id' => $photo->id,
            'sketch_id' => $photo->sketch_id,
        ], 201);
    }

    public function destroy(Request $request, PhotoPreview $preview): JsonResponse
    {
        abort_if($preview->user_id !== $request->user()->id, 403);

        $this->previewService->deletePreview($preview);

        return response()->json(null, 204);
    }
}
