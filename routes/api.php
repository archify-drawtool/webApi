<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\NodeTypeController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SharedLinkController;
use App\Http\Controllers\SketchController;
use Illuminate\Support\Facades\Route;
use Spatie\Prometheus\Http\Controllers\PrometheusMetricsController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::get('/metrics', PrometheusMetricsController::class);

Route::post('/login', [AuthController::class, 'login']);
Route::get('/shared/node-types', [SharedLinkController::class, 'nodesTypes']);
Route::get('/shared/{token}', [SharedLinkController::class, 'show']);
Route::get('/shared/{token}/photo', [SharedLinkController::class, 'showPhoto']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::patch('/user', [AuthController::class, 'updatePreferences']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/node-types', [NodeTypeController::class, 'index']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/photos/upload', [PhotoController::class, 'upload']);
    Route::get('/photos/{photo}/status', [PhotoController::class, 'status']);
    Route::get('/sketches', [SketchController::class, 'userIndex']);
    Route::post('/sketches', [SketchController::class, 'store']);
    Route::get('/sketches/{sketch}', [SketchController::class, 'show']);
    Route::get('/sketches/{sketch}/photo', [SketchController::class, 'showPhoto']);
    Route::put('/sketches/{sketch}', [SketchController::class, 'updateCanvas']);
    Route::patch('/sketches/{sketch}/rename', [SketchController::class, 'renameSketch']);
    Route::delete('/sketches/{sketch}', [SketchController::class, 'destroySketch']);
    Route::get('/sketches/{sketch}/export/mermaid', [SketchController::class, 'exportMermaidSketch']);
    Route::get('/projects/{project}/sketches', [SketchController::class, 'index']);
    Route::post('/export/mermaid', [SketchController::class, 'exportMermaidFromState']);
    Route::get('/projects/{project}/sketches/{sketch}/share', [SharedLinkController::class, 'status']);
    Route::post('/projects/{project}/sketches/{sketch}/share', [SharedLinkController::class, 'toggle']);
    Route::get('/sketches/{sketch}/comments', [CommentController::class, 'index']);
    Route::post('/sketches/{sketch}/comments', [CommentController::class, 'store']);
    Route::patch('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
});
