<?php

namespace App\Http\Controllers;

use App\Services\ArucoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function __construct(private ArucoService $arucoService) {}

    public function upload(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:10240',
        ]);

        $extension = $request->file('photo')->getClientOriginalExtension();
        $filename = now()->timezone('Europe/Amsterdam')->format('Y-m-d_H-i-s_v').'.'.$extension;

        $path = $request->file('photo')->storeAs('photos', $filename, 'local');

        try {
            $absolutePath = Storage::disk('local')->path($path);
            $markers = $this->arucoService->detectMarkers($absolutePath);

            Log::info('[ArUco] Detection complete', [
                'file' => $filename,
                'marker_count' => count($markers),
                'markers' => $markers,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ArUco] Detection failed', [
                'file' => $filename,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'path' => $path,
        ], 201);
    }
}
