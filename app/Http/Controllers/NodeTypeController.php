<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class NodeTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(config('node_types'));
    }
}
