<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NodeTypeController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/node-types', [NodeTypeController::class, 'index']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::get('/photos/{filename}/aruco', [PhotoController::class, 'getArucoResults']);
});

Route::post('/photos/upload', [PhotoController::class, 'upload']);
