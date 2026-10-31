<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\Api\TunnelController;
use Illuminate\Support\Facades\Route;

// Global Public Connectivity Check (used across all pages)
Route::get('/api/connectivity-check', function () {
    $connected = false;
    $socket = @fsockopen('1.1.1.1', 53, $errno, $errstr, 1.5);
    if ($socket) {
        $connected = true;
        fclose($socket);
    } else {
        $socket2 = @fsockopen('8.8.8.8', 53, $errno, $errstr, 1.5);
        if ($socket2) {
            $connected = true;
            fclose($socket2);
        }
    }

    return response()->json([
        'online' => $connected,
        'timestamp' => now()->toIso8601String(),
    ]);
});

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

    // License Sync, Upgrades & Diagnostic Test Flow
    Route::get('/api/license/sync', [LicenseController::class, 'sync'])->name('license.sync');
    Route::post('/api/license/upgrade-test', [LicenseController::class, 'upgradeTest'])->name('license.upgrade.test');
    Route::post('/api/license/test-flow', [LicenseController::class, 'runTestFlow'])->name('license.test.flow');
    Route::post('/api/webhook/license-update', [LicenseController::class, 'webhook']);

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

    // Include Pro Custom Domains feature routes if addon is installed
    if (file_exists(__DIR__ . '/domains.php')) {
        require __DIR__ . '/domains.php';
    }
});
