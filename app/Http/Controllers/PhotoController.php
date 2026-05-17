<?php

namespace App\Http\Controllers;

use App\Enums\PhotoStatus;
use App\Models\ArucoMarker;
use App\Models\ArucoMarkerCorner;
use App\Models\DetectedEdge;
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

    public function getArucoResults(int $sketch_id): JsonResponse
    {
        $result = $this->photoService->getDetectionResultBySketchId($sketch_id);

        if ($result === null) {
            return response()->json(
                ['message' => 'No detection result found for this sketch.'],
                404
            );
        }

        $markerConfig = config('marker_config');

        $markers = $result->markers->map(function (ArucoMarker $marker) use ($markerConfig) {
            $cfg = $markerConfig[$marker->marker_id] ?? [
                'type' => 'node',
                'hitbox' => ['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0],
            ];

            return [
                'id' => $marker->id,
                'marker_id' => $marker->marker_id,
                'center_x' => $marker->center_x,
                'center_y' => $marker->center_y,
                'rotation' => $marker->rotation,
                'ocr_text' => $marker->ocr_text,
                'corners' => $marker->corners->map(fn (ArucoMarkerCorner $c) => [
                    'position' => $c->position,
                    'x' => $c->x,
                    'y' => $c->y,
                ])->values(),
                'type' => $cfg['type'],
                'hitbox' => $cfg['hitbox'],
            ];
        })->values();

        $edges = $result->edges->map(fn (DetectedEdge $edge) => [
            'id' => $edge->id,
            'edge_type' => $edge->edge_type,
            'edge_marker_id' => $edge->edge_marker_id,
            'source_marker_id' => $edge->source_marker_id,
            'target_marker_id' => $edge->target_marker_id,
        ])->values();

        return response()->json([
            'markers' => $markers,
            'edges' => $edges,
            'config' => [
                'edge_margin' => (float) config('aruco.edge_margin'),
                'edge_angle_margin' => (float) config('aruco.edge_angle_margin'),
            ],
        ]);
    }
}
