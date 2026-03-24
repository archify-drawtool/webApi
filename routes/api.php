<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NodeTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::post('/login', [AuthController::class, 'login']);

Route::get('/node-types', [NodeTypeController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
