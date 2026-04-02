<?php

namespace App\Http\Controllers;

use App\Models\Sketch;
use Illuminate\Http\JsonResponse;

class SketchController extends Controller
{
    /**
     * Get a single sketch by ID.
     */
    public function show(Sketch $sketch): JsonResponse
    {
        $sketch->load('creator:id,name,email');

        return response()->json($sketch);
    }
}
