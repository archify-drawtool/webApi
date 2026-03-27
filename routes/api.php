<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NodeTypeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhotoController;

Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/node-types', [NodeTypeController::class, 'index']);
    Route::post('/photos/upload', [PhotoController::class, 'upload']);
});
