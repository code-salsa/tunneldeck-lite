<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TunnelController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Licensing Platform Sync Webhook: Reset to defaults on client deletion
Route::post('/license/client-deleted', [\App\Http\Controllers\LicenseController::class, 'clientDeletedWebhook']);
Route::post('/client-deleted', [\App\Http\Controllers\LicenseController::class, 'clientDeletedWebhook']);
Route::post('/webhook/client-deleted', [\App\Http\Controllers\LicenseController::class, 'clientDeletedWebhook']);

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
    Route::post('/user/password', [AuthController::class, 'updatePassword']);

    // Tunnel Infrastructure endpoints
    Route::get('/tunnels', [TunnelController::class, 'index']);
    Route::get('/tunnels/limit', [TunnelController::class, 'limit']);
    Route::get('/stats', [TunnelController::class, 'stats']);
    Route::post('/tunnels', [TunnelController::class, 'store']);
    Route::put('/tunnels/{tunnel}', [TunnelController::class, 'update']);
    Route::post('/tunnels/{tunnel}/start', [TunnelController::class, 'start']);
    Route::post('/tunnels/{tunnel}/stop', [TunnelController::class, 'stop']);
    Route::post('/tunnels/{tunnel}/restart', [TunnelController::class, 'restart']);
    Route::patch('/tunnels/{tunnel}/toggle', [TunnelController::class, 'toggleStatus']);
    Route::get('/tunnels/{tunnel}/status', [TunnelController::class, 'status']);
    Route::post('/tunnels/bulk-delete', [TunnelController::class, 'bulkDestroy']);
    Route::delete('/tunnels/bulk-delete', [TunnelController::class, 'bulkDestroy']);
    Route::delete('/tunnels/{tunnel}', [TunnelController::class, 'destroy']);

    // Include Pro Custom Domains API endpoints if addon is installed
    if (file_exists(base_path('routes/domains.php')) && class_exists(\App\Http\Controllers\Api\DomainController::class)) {
        Route::get('/domains', [\App\Http\Controllers\Api\DomainController::class, 'index']);
        Route::post('/domains', [\App\Http\Controllers\Api\DomainController::class, 'store']);
        Route::post('/domains/{id}/validate', [\App\Http\Controllers\Api\DomainController::class, 'validateDomain']);
        Route::delete('/domains/{id}', [\App\Http\Controllers\Api\DomainController::class, 'destroy']);
        Route::post('/cloudflare/login', [\App\Http\Controllers\Api\DomainController::class, 'loginCloudflare']);
        Route::get('/cloudflare/status', [\App\Http\Controllers\Api\DomainController::class, 'cloudflareStatus']);
    }
});
