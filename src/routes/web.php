<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\TunnelController;
use Illuminate\Support\Facades\Route;

// Guest authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login']);
});

// Protected web application routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::post('/user/password', [WebAuthController::class, 'updatePassword'])->name('user.password');

    // Onboarding routes
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('/onboarding/explore', [OnboardingController::class, 'explore'])->name('onboarding.explore');

    // Tunnel AJAX routes (Web Session protected)
    Route::get('/api/tunnels', [TunnelController::class, 'index']);
    Route::get('/api/stats', [TunnelController::class, 'stats']);
    Route::post('/api/tunnels', [TunnelController::class, 'store']);
    Route::put('/api/tunnels/{tunnel}', [TunnelController::class, 'update']);
    Route::post('/api/tunnels/{tunnel}/start', [TunnelController::class, 'start']);
    Route::post('/api/tunnels/{tunnel}/stop', [TunnelController::class, 'stop']);
    Route::post('/api/tunnels/{tunnel}/restart', [TunnelController::class, 'restart']);
    Route::patch('/api/tunnels/{tunnel}/toggle', [TunnelController::class, 'toggleStatus']);
    Route::get('/api/tunnels/{tunnel}/status', [TunnelController::class, 'status']);
    Route::delete('/api/tunnels/{tunnel}', [TunnelController::class, 'destroy']);

    // Domain AJAX routes (Web Session protected)
    Route::get('/api/domains', [DomainController::class, 'index']);
    Route::post('/api/domains', [DomainController::class, 'store']);
    Route::post('/api/domains/{id}/validate', [DomainController::class, 'validateDomain']);
    Route::delete('/api/domains/{id}', [DomainController::class, 'destroy']);
    Route::post('/api/cloudflare/login', [DomainController::class, 'loginCloudflare']);
    Route::get('/api/cloudflare/status', [DomainController::class, 'cloudflareStatus']);
});
