<?php

namespace App\Http\Controllers;

use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct(private readonly PhotoService $photoService) {}

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:10240',
        ]);

        $path = $this->photoService->store($request->file('photo'));

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'path'    => $path,
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
