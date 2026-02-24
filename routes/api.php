<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'My Project API',
        'status' => 'running',
        'version' => '1.0.0',
    ]);
});
