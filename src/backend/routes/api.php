<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TunnelController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

Route::get('/health', function () {
    return response()->json([
        'status' => 'operational',
        'database' => 'sqlite',
        'version' => app()->version(),
        'app' => 'TunnelDeck Lite',
        'timestamp' => now()->toISOString(),
    ]);
});

// Protected routes (Sanctum authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/tunnels', [TunnelController::class, 'index']);
    Route::post('/tunnels', [TunnelController::class, 'store']);
    Route::patch('/tunnels/{tunnel}/toggle', [TunnelController::class, 'toggleStatus']);
    Route::delete('/tunnels/{tunnel}', [TunnelController::class, 'destroy']);
});
