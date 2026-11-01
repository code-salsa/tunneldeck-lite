<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\Api\TunnelController;
use Illuminate\Support\Facades\Route;

// Global Public Connectivity Check (used across all pages)
Route::get('/api/connectivity-check', function () {
    $connected = false;

    // Fast probe 1: System UDP DNS query for high-availability hosts (~5-15ms)
    if (@checkdnsrr('cloudflare.com', 'A') || @checkdnsrr('google.com', 'A')) {
        $connected = true;
    }

    // Fast probe 2: Outbound HTTPS port 443 sockets (almost never blocked by egress firewalls)
    if (!$connected) {
        $targets = [
            ['1.1.1.1', 443],
            ['8.8.8.8', 443],
            ['1.0.0.1', 443],
            ['licensing.tunneldeck.online', 443],
        ];

        foreach ($targets as [$host, $port]) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);
            if ($socket) {
                $connected = true;
                fclose($socket);
                break;
            }
        }
    }

    // Fast probe 3: Standard DNS port 53 fallback
    if (!$connected) {
        $socket = @fsockopen('1.1.1.1', 53, $errno, $errstr, 1.0) ?: @fsockopen('8.8.8.8', 53, $errno, $errstr, 1.0);
        if ($socket) {
            $connected = true;
            fclose($socket);
        }
    }

    return response()->json([
        'online' => $connected,
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Licensing Platform Sync Webhook: Reset to defaults on client deletion (Public Webhook)
Route::post('/api/license/client-deleted', [LicenseController::class, 'clientDeletedWebhook']);
Route::post('/api/webhook/client-deleted', [LicenseController::class, 'clientDeletedWebhook']);

// Guest authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login']);
});

// Protected web application routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/suspended', [\App\Http\Controllers\SuspensionController::class, 'show'])->name('account.suspended');
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::post('/user/password', [WebAuthController::class, 'updatePassword'])->name('user.password');

    // Onboarding routes
    Route::post('/onboarding/send-otp', [OnboardingController::class, 'sendOtp'])->name('onboarding.send-otp');
    Route::post('/onboarding/verify-otp', [OnboardingController::class, 'verifyOtp'])->name('onboarding.verify-otp');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('/onboarding/explore', [OnboardingController::class, 'explore'])->name('onboarding.explore');

    // License Sync, Upgrades & Diagnostic Test Flow
    Route::get('/api/license/sync', [LicenseController::class, 'sync'])->name('license.sync');
    Route::get('/api/license/device-info', [LicenseController::class, 'deviceInfo'])->name('license.device.info');
    Route::get('/api/license/seats', [LicenseController::class, 'seatsInfo'])->name('license.seats.info');
    Route::post('/api/license/upgrade-test', [LicenseController::class, 'upgradeTest'])->name('license.upgrade.test');
    Route::post('/api/license/install-pro-package', [LicenseController::class, 'installProPackage'])->name('license.install.pro-package');
    Route::post('/api/license/test-flow', [LicenseController::class, 'runTestFlow'])->name('license.test.flow');
    Route::post('/api/webhook/license-update', [LicenseController::class, 'webhook']);

    // Payment Gateway & Checkout routes (Razorpay Pro Upgrade & Buy Coffee)
    Route::get('/api/payment/config', [PaymentController::class, 'config'])->name('payment.config');
    Route::post('/api/payment/pro-success', [PaymentController::class, 'proSuccess'])->name('payment.pro.success');
    Route::post('/api/payment/coffee-success', [PaymentController::class, 'coffeeSuccess'])->name('payment.coffee.success');

    // Tunnel AJAX routes (Web Session protected)
    Route::get('/api/tunnels', [TunnelController::class, 'index']);
    Route::get('/api/tunnels/limit', [TunnelController::class, 'limit']);
    Route::get('/api/tunnels/check-port/{port}', [TunnelController::class, 'checkPort'])->name('tunnels.check-port');
    Route::get('/api/stats', [TunnelController::class, 'stats']);
    Route::post('/api/tunnels', [TunnelController::class, 'store']);
    Route::put('/api/tunnels/{tunnel}', [TunnelController::class, 'update']);
    Route::post('/api/tunnels/{tunnel}/start', [TunnelController::class, 'start']);
    Route::post('/api/tunnels/{tunnel}/stop', [TunnelController::class, 'stop']);
    Route::post('/api/tunnels/{tunnel}/restart', [TunnelController::class, 'restart']);
    Route::patch('/api/tunnels/{tunnel}/toggle', [TunnelController::class, 'toggleStatus']);
    Route::get('/api/tunnels/{tunnel}/status', [TunnelController::class, 'status']);
    Route::post('/api/tunnels/bulk-delete', [TunnelController::class, 'bulkDestroy']);
    Route::delete('/api/tunnels/bulk-delete', [TunnelController::class, 'bulkDestroy']);
    Route::delete('/api/tunnels/{tunnel}', [TunnelController::class, 'destroy']);

    // Include Pro Custom Domains feature routes if addon is installed
    if (file_exists(__DIR__ . '/domains.php')) {
        require __DIR__ . '/domains.php';
    }
});
