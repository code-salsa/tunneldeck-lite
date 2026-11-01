<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class TunnelDeckService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.tunneldeck.url', 'https://licensing.tunneldeck.online/api/v1'), '/');
        $this->token = config('services.tunneldeck.token', 'td_live_0b22af8832fc33b46b2001073347bb832c9b68c59eed1eb8');
    }

    protected function client()
    {
        $hwid = app(HardwareFingerprintService::class)->getHwid();

        return Http::withToken($this->token)
            ->withHeaders([
                'X-DEVICE-HWID' => $hwid,
            ])
            ->acceptJson()
            ->baseUrl($this->baseUrl)
            ->timeout(4);
    }

    /**
     * 1. Register a new user (Currency defaults to INR for India, USD for others. Package defaults to lite).
     * Includes full system telemetry (OS, IP, Location, HWID, Hostname).
     */
    public function registerUser(array $data): ?Response
    {
        try {
            $sys = app(SystemInfoService::class)->getSystemInfo(request());
            $data['hwid'] = $data['hwid'] ?? $sys['hwid'];
            $data['os'] = $data['os'] ?? $sys['os'];
            $data['ip_address'] = $data['ip_address'] ?? $sys['ip_address'];
            $data['city'] = !empty($data['city']) ? $data['city'] : $sys['city'];
            $data['country'] = !empty($data['country']) ? $data['country'] : $sys['country'];
            $data['hostname'] = $data['hostname'] ?? $sys['hostname'];
            $data['system_info'] = array_merge($sys, $data['system_info'] ?? []);

            return $this->client()->post('/users/register', $data);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService registerUser error: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }

    /**
     * Complete user onboarding on licensing server and issue default Lite license key.
     * Includes full system telemetry.
     */
    public function completeOnboarding(string $userEmailOrId, array $data = []): ?Response
    {
        try {
            $sys = app(SystemInfoService::class)->getSystemInfo(request());
            $data['hwid'] = $data['hwid'] ?? $sys['hwid'];
            $data['os'] = $data['os'] ?? $sys['os'];
            $data['ip_address'] = $data['ip_address'] ?? $sys['ip_address'];
            $data['city'] = !empty($data['city']) ? $data['city'] : $sys['city'];
            $data['country'] = !empty($data['country']) ? $data['country'] : $sys['country'];
            $data['hostname'] = $data['hostname'] ?? $sys['hostname'];
            $data['system_info'] = array_merge($sys, $data['system_info'] ?? []);

            return $this->client()->post("/users/{$userEmailOrId}/complete-onboarding", $data);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService completeOnboarding error: ' . $e->getMessage(), ['email' => $userEmailOrId, 'data' => $data]);
            return null;
        }
    }

    /**
     * Dispatch 4-digit verification OTP email to user via licensing server.
     */
    public function sendOtp(string $email, ?string $name = null): ?Response
    {
        try {
            return $this->client()->post('/auth/send-otp', [
                'email' => $email,
                'name' => $name,
            ]);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService sendOtp error: ' . $e->getMessage(), ['email' => $email]);
            return null;
        }
    }

    /**
     * Verify 4-digit OTP code with licensing server.
     */
    public function verifyOtp(string $email, string $otp): ?Response
    {
        try {
            return $this->client()->post('/auth/verify-otp', [
                'email' => $email,
                'otp' => $otp,
            ]);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService verifyOtp error: ' . $e->getMessage(), ['email' => $email, 'otp' => $otp]);
            return null;
        }
    }

    /**
     * Report user exploring telemetry to the licensing platform.
     */
    public function reportExploringTelemetry(\App\Models\User $user, array $additional = []): ?Response
    {
        $payload = array_merge([
            'email' => $user->email,
            'name' => ($user->name && $user->name !== 'TunnelDeck Admin') ? $user->name : 'Platform Explorer',
            'city' => $user->city,
            'country' => $user->country,
            'package' => $user->package ?? 'lite',
            'onboarding_status' => 'exploring',
            'is_onboarded' => false,
        ], $additional);

        return $this->registerUser($payload);
    }

    /**
     * 2. Log user access (e.g. after login).
     */
    public function logAccess(string $userEmailOrId, array $payload = []): ?Response
    {
        try {
            return $this->client()->post("/users/{$userEmailOrId}/access-logs", $payload);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService logAccess error: ' . $e->getMessage(), ['payload' => $payload]);
            return null;
        }
    }

    /**
     * 3. Log user activity or feature event.
     */
    public function logActivity(string $userEmailOrId, string $action, ?string $description = null, array $metadata = []): ?Response
    {
        try {
            return $this->client()->post("/users/{$userEmailOrId}/activity-logs", [
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService logActivity error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 4. Record package upgrade.
     */
    public function logUpgrade(string $userEmailOrId, string $toPackage, float $amount = 0.0, array $extra = []): ?Response
    {
        try {
            return $this->client()->post("/users/{$userEmailOrId}/upgrades", array_merge([
                'to_package' => $toPackage,
                'amount' => $amount,
            ], $extra));
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService logUpgrade error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 5. Get user profile and license status from licensing server.
     */
    public function getUser(string $userEmailOrId): ?Response
    {
        try {
            return $this->client()->get("/users/{$userEmailOrId}");
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService getUser error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 6. Fetch user's package upgrade history.
     */
    public function getUserUpgrades(string $userEmailOrId): ?Response
    {
        try {
            return $this->client()->get("/users/{$userEmailOrId}/upgrades");
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService getUserUpgrades error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 7. Fetch user's access logs.
     */
    public function getUserAccessLogs(string $userEmailOrId): ?Response
    {
        try {
            return $this->client()->get("/users/{$userEmailOrId}/access-logs");
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService getUserAccessLogs error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 8. Fetch user's activity logs.
     */
    public function getUserActivityLogs(string $userEmailOrId): ?Response
    {
        try {
            return $this->client()->get("/users/{$userEmailOrId}/activity-logs");
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService getUserActivityLogs error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 9. Fetch user activation seats and bound machine HWIDs from licensing server.
     */
    public function getUserActivations(string $userEmailOrId): ?Response
    {
        try {
            return $this->client()->get("/users/{$userEmailOrId}/activations");
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService getUserActivations error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 10. Unbind a machine HWID seat from licensing server.
     */
    public function unbindUserActivation(string $userEmailOrId, ?string $hwid = null): ?Response
    {
        try {
            $hwid = $hwid ?: app(HardwareFingerprintService::class)->getHwid();
            return $this->client()->post("/users/{$userEmailOrId}/activations/unbind", [
                'hwid' => $hwid,
            ]);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService unbindUserActivation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 11. Synchronize user's license and package tier from cloud to local instance.
     */
    public function syncUserLicense(\App\Models\User $user): array
    {
        $response = $this->getUser($user->email);

        // 1. Client Deletion Detection & Auto-Reset to Factory Defaults (Only for onboarded users, NOT default admin exploring)
        if ($user->is_onboarded && strtolower($user->email) !== 'admin@tunneldeck.online') {
            if ($response && ($response->status() === 404 || (bool) $response->json('client_deleted') || (bool) $response->json('user_deleted'))) {
                Log::channel('tunnels')->warning("TunnelDeckService: Client {$user->email} was deleted on licensing platform. Automatically resetting database to factory defaults.");
                static::resetToDefaults();

                return [
                    'success' => false,
                    'client_deleted' => true,
                    'reset_to_defaults' => true,
                    'redirect' => '/login',
                    'message' => 'Client was removed from the licensing platform. Local instance has been reset to factory defaults.',
                ];
            }
        } elseif (!$response || !$response->successful()) {
            // User is exploring/simulation mode and not registered on licensing platform
            return [
                'success' => true,
                'is_onboarded' => false,
                'package' => $user->package ?? 'lite',
                'message' => 'Local superadmin in preview/exploration mode.',
            ];
        }

        if (!$response || !$response->successful() || !$response->json('success')) {
            return [
                'success' => false,
                'message' => 'Unable to fetch license from licensing server.',
                'package' => $user->package,
            ];
        }

        $data = $response->json('data') ?? [];
        $cloudPackage = strtolower((string) ($data['package'] ?? 'lite'));
        $cloudStatus = strtolower((string) ($data['status'] ?? 'active'));
        $cloudCurrency = $data['currency'] ?? null;
        $oldPackage = $user->package;
        $oldStatus = $user->status ?? 'active';

        // 1. Suspension Detection & Enforcement
        if (in_array($cloudStatus, ['suspended', 'inactive', 'revoked'])) {
            $user->status = 'suspended';
            $user->save();

            // Stop all active tunnels immediately on suspension
            try {
                app(\App\Services\TunnelManagerService::class)->stopAll();
            } catch (\Throwable $e) {
                // ignore
            }

            if ($oldStatus !== 'suspended') {
                \App\Models\Activity::create([
                    'user_id' => $user->id,
                    'description' => "Account access suspended by licensing administrator.",
                    'type' => 'error',
                ]);
            }

            return [
                'success' => false,
                'suspended' => true,
                'status' => 'suspended',
                'message' => 'Your account access has been suspended by the administrator.',
                'package' => $cloudPackage,
                'data' => $data,
            ];
        }

        // 2. Max Allowed Activations (Seats / Devices Allowed) Limit Enforcement
        $isSeatLimitReached = !empty($data['activation_limit_reached'])
            || $cloudStatus === 'activation_limit_reached'
            || (!empty($data['activation']['limit_reached']));

        if ($isSeatLimitReached) {
            $user->package = 'lite';
            $user->status = 'active';
            $user->save();

            \App\Models\Setting::forget('license_certificate');
            \App\Models\Setting::forget('license_signature');
            \App\Models\Setting::forget('license_tier');

            $maxSeats = $data['max_activations'] ?? 1;
            \App\Models\Activity::create([
                'user_id' => $user->id,
                'description' => "Device activation seat limit reached ({$maxSeats} allowed). Pro features locked on this device.",
                'type' => 'warning',
            ]);

            return [
                'success' => false,
                'activation_limit_reached' => true,
                'max_activations' => $maxSeats,
                'activations_count' => $data['activations_count'] ?? 1,
                'available_seats' => $data['available_seats'] ?? 0,
                'message' => "Maximum allowed activations ({$maxSeats} seats) reached. Number of machines or devices allowed has been exceeded.",
                'package' => 'lite',
                'data' => $data,
            ];
        }

        // 3. Account Re-activation if restored
        if ($oldStatus === 'suspended' && $cloudStatus === 'active') {
            $user->status = 'active';
            \App\Models\Activity::create([
                'user_id' => $user->id,
                'description' => "Account access restored by licensing administrator.",
                'type' => 'success',
            ]);
        }

        // 3. Store and cryptographically verify signed license if provided by server
        if (!empty($data['signed_license']) && is_array($data['signed_license'])) {
            app(\App\Services\LicenseSignatureVerifier::class)->storeSignedLicense($data['signed_license'], $user);
        }

        $user->package = $cloudPackage;
        $user->status = 'active';
        if ($cloudCurrency) {
            $user->currency = $cloudCurrency;
        }

        // Synchronize any recent coffee donations from cloud activity logs
        if (isset($data['activity_logs']) && is_array($data['activity_logs'])) {
            foreach ($data['activity_logs'] as $log) {
                if (($log['action'] ?? '') === 'coffee_donation' && isset($log['created_at'])) {
                    $donationDate = \Carbon\Carbon::parse($log['created_at']);
                    if (!$user->coffee_donated_at || $donationDate->greaterThan($user->coffee_donated_at)) {
                        $user->coffee_donated_at = $donationDate;
                    }
                }
            }
        }

        $user->license_synced_at = now();
        $user->save();

        if ($oldPackage !== $cloudPackage) {
            \App\Models\Activity::create([
                'user_id' => $user->id,
                'description' => "Plan upgraded from {$oldPackage} to {$cloudPackage} via licensing server sync",
                'type' => 'success',
            ]);
        }

        return [
            'success' => true,
            'suspended' => false,
            'status' => 'active',
            'message' => "License synchronized successfully. Current plan: " . strtoupper($cloudPackage),
            'package' => $cloudPackage,
            'currency' => $cloudCurrency,
            'upgraded' => ($oldPackage !== $cloudPackage),
            'synced_at' => $user->license_synced_at->toIso8601String(),
            'data' => $data,
        ];
    }

    /**
     * 10. Get payment gateway credentials and checkout parameters from licensing server.
     */
    public function getPaymentConfig(): array
    {
        try {
            $resp = $this->client()->get('/payment/config');
            if ($resp->successful() && $resp->json('success')) {
                return $resp->json();
            }

            // Fallback to authenticated details endpoint if public config was empty
            $resp2 = $this->client()->get('/payment-gateway/details');
            if ($resp2->successful() && $resp2->json('success')) {
                $data = $resp2->json();
                return [
                    'success' => true,
                    'gateway' => $data['gateway'] ?? 'razorpay',
                    'name' => $data['name'] ?? 'Razorpay',
                    'is_enabled' => $data['is_enabled'] ?? true,
                    'mode' => $data['mode'] ?? 'test',
                    'key_id' => $data['active_credentials']['key_id'] ?? null,
                    'currency' => $data['currency'] ?? 'INR',
                    'pro_plan' => [
                        'name' => 'Pro',
                        'price' => 1499.00,
                        'price_inr' => 1499.00,
                        'price_usd' => 19.00,
                        'currency' => $data['currency'] ?? 'INR',
                        'features' => ['Unlimited active tunnels', 'Unlimited custom domains'],
                    ],
                ];
            }
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService getPaymentConfig error: ' . $e->getMessage());
        }

        // Default fallback if server is momentarily unreachable
        return [
            'success' => true,
            'gateway' => 'razorpay',
            'name' => 'Razorpay',
            'is_enabled' => true,
            'mode' => 'test',
            'key_id' => 'rzp_test_TuXla2n6Mm2F27',
            'currency' => 'INR',
            'pro_plan' => [
                'name' => 'Pro',
                'price' => 1499.00,
                'price_inr' => 1499.00,
                'price_usd' => 19.00,
                'currency' => 'INR',
                'features' => ['Unlimited active tunnels', 'Unlimited custom domains'],
            ],
        ];
    }

    /**
     * 11. Send payment success callback to licensing server (Pro auto-upgrade or Coffee donation).
     */
    public function recordPaymentSuccess(array $payload): ?Response
    {
        try {
            $payload['hwid'] = $payload['hwid'] ?? app(HardwareFingerprintService::class)->getHwid();
            return $this->client()->post('/payment/success', $payload);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService recordPaymentSuccess error: ' . $e->getMessage(), ['payload' => $payload]);
            return null;
        }
    }

    /**
     * Fetch the official RSA Public Key from the licensing server.
     */
    public function fetchPublicKeyFromCloud(): ?string
    {
        try {
            $resp = $this->client()->get('/license/public-key');
            if ($resp->successful() && $resp->json('success')) {
                $pubKey = $resp->json('public_key');
                if (!empty($pubKey)) {
                    \App\Models\Setting::set('licensing_public_key', $pubKey);
                    return $pubKey;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TunnelDeckService fetchPublicKeyFromCloud error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * 12. Check tunnel creation limit from licensing API and current workspace usage.
     */
    public function getTunnelLimit(\App\Models\User $user): array
    {
        if (method_exists($user, 'isSuspended') && $user->isSuspended()) {
            return [
                'max_tunnels' => 0,
                'is_unlimited' => false,
                'total_created' => \App\Models\Tunnel::count(),
                'remaining' => 0,
                'limit_reached' => true,
                'package' => 'suspended',
                'suspended' => true,
                'message' => 'Your account access has been suspended by the administrator.',
            ];
        }

        $userIsPro = (method_exists($user, 'isPro') && $user->isPro()) || (method_exists($user, 'isUnlimitedTunnels') && $user->isUnlimitedTunnels());
        $package = $userIsPro ? strtolower($user->package ?? 'pro') : 'lite';
        $maxTunnels = $userIsPro ? -1 : 5;
        $isUnlimited = $userIsPro;

        // Check licensing server for plan limits
        try {
            $resp = $this->getUser($user->email);
            if ($resp && $resp->successful()) {
                $cloudData = $resp->json('data') ?? [];
                if (!empty($cloudData['signed_license']) && is_array($cloudData['signed_license'])) {
                    app(\App\Services\LicenseSignatureVerifier::class)->storeSignedLicense($cloudData['signed_license'], $user);
                }
                if (isset($cloudData['package'])) {
                    $cloudPackage = strtolower($cloudData['package']);
                    if (in_array($cloudPackage, ['pro', 'enterprise']) && $user->isPro()) {
                        $package = $cloudPackage;
                        $isUnlimited = true;
                    } elseif ($cloudPackage === 'lite' || !$user->isPro()) {
                        $package = 'lite';
                        $isUnlimited = false;
                    }
                }
                if (isset($cloudData['max_tunnels']) && !$isUnlimited) {
                    $maxTunnels = (int) $cloudData['max_tunnels'];
                }
            }
        } catch (\Throwable $e) {
            // fallback to local defaults
        }

        if ($isUnlimited) {
            $maxTunnels = -1;
        } elseif ($maxTunnels <= 0) {
            $maxTunnels = 5;
        }

        $userId = $user->id;
        $totalCreated = \App\Models\Tunnel::where(function ($q) use ($userId) {
            if ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            }
        })->count();

        $limitReached = !$isUnlimited && ($maxTunnels > 0) && ($totalCreated >= $maxTunnels);
        $remaining = $isUnlimited ? -1 : max(0, $maxTunnels - $totalCreated);

        return [
            'max_tunnels' => $maxTunnels,
            'is_unlimited' => $isUnlimited,
            'total_created' => $totalCreated,
            'remaining' => $remaining,
            'limit_reached' => $limitReached,
            'package' => $package,
            'message' => $limitReached
                ? 'You have reached the number of allowed tunnel creation. Upgrade to a pro plan to enjoy unlimited tunnels.'
                : null,
        ];
    }

    /**
     * Reset local database and platform state to factory defaults.
     * Invoked when a client is deleted from licensing platform.
     */
    public static function resetToDefaults(): array
    {
        Log::channel('tunnels')->info('TunnelDeckService: Resetting TunnelDeck Lite database to factory defaults...');

        // 1. Stop all active tunnels safely
        try {
            if (class_exists(\App\Services\TunnelManagerService::class)) {
                app(\App\Services\TunnelManagerService::class)->stopAll();
            }
        } catch (\Throwable $e) {
            Log::warning('Error stopping tunnels during resetToDefaults: ' . $e->getMessage());
        }

        // 2. Clear dynamic tables
        try {
            \App\Models\Tunnel::query()->delete();
        } catch (\Throwable $e) {}

        try {
            if (class_exists(\App\Models\CustomDomain::class)) {
                \App\Models\CustomDomain::query()->delete();
            }
        } catch (\Throwable $e) {}

        try {
            \App\Models\Activity::query()->delete();
        } catch (\Throwable $e) {}

        try {
            \App\Models\Setting::query()->delete();
        } catch (\Throwable $e) {}

        // 3. Delete all existing user accounts
        try {
            \App\Models\User::query()->delete();
        } catch (\Throwable $e) {}

        // 4. Re-run DatabaseSeeder to provision fresh superadmin and default sample tunnels
        try {
            $seeder = new \Database\Seeders\DatabaseSeeder();
            $seeder->run();
        } catch (\Throwable $e) {
            Log::error('Error executing DatabaseSeeder in resetToDefaults: ' . $e->getMessage());
        }

        // 5. Invalidate current auth session if present
        try {
            if (\Illuminate\Support\Facades\Auth::check()) {
                \Illuminate\Support\Facades\Auth::logout();
            }
            if (session()) {
                session()->forget(['is_exploring']);
                session()->flush();
            }
        } catch (\Throwable $e) {}

        return [
            'success' => true,
            'message' => 'TunnelDeck Lite database has been reset to factory defaults.',
            'reset_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Download the Pro package ZIP archive from licensing API.
     */
    public function downloadProPackage(string $userEmailOrId): ?Response
    {
        try {
            $hwid = app(HardwareFingerprintService::class)->getHwid();
            return Http::withToken($this->token)
                ->withHeaders([
                    'X-DEVICE-HWID' => $hwid,
                ])
                ->baseUrl($this->baseUrl)
                ->timeout(60)
                ->get("/users/{$userEmailOrId}/pro-package/download");
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService downloadProPackage error: ' . $e->getMessage(), ['user' => $userEmailOrId]);
            return null;
        }
    }

    /**
     * Unpack and merge Pro edition files into the project src directory.
     */
    public static function extractAndMergeProPackage(string $zipContent): array
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempZip = $tempDir . DIRECTORY_SEPARATOR . 'pro_build_' . time() . '.zip';
        file_put_contents($tempZip, $zipContent);

        $extractedFiles = [];
        $baseDir = base_path(); // Points to d:\FINAL SAAS PROJECTS\tunneldeck-lite\src

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($tempZip) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (str_ends_with($name, '/') || str_ends_with($name, '\\')) {
                        continue;
                    }

                    $normalized = str_replace('\\', '/', $name);
                    $relative = str_starts_with($normalized, 'src/') ? substr($normalized, 4) : $normalized;

                    $dest = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    $destDir = dirname($dest);
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }

                    $fileContent = $zip->getFromIndex($i);
                    if ($fileContent !== false) {
                        file_put_contents($dest, $fileContent);
                        $extractedFiles[] = $relative;
                    }
                }
                $zip->close();
            }
        } else {
            // Fallback via system tar
            $extractTemp = $tempDir . DIRECTORY_SEPARATOR . 'extracted_' . time();
            mkdir($extractTemp, 0755, true);
            exec("tar -xf " . escapeshellarg($tempZip) . " -C " . escapeshellarg($extractTemp));

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($extractTemp, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($extractTemp) + 1));
                    $targetRel = str_starts_with($rel, 'src/') ? substr($rel, 4) : $rel;
                    $dest = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRel);
                    $destDir = dirname($dest);
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    copy($item->getPathname(), $dest);
                    $extractedFiles[] = $targetRel;
                }
            }
        }

        @unlink($tempZip);

        // Run migrations if custom domains or new schema was merged
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::warning('Artisan migrate post-pro-upgrade: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'files_count' => count($extractedFiles),
            'files' => $extractedFiles,
        ];
    }

    /**
     * Download Pro package from licensing server API and merge into project src.
     */
    public function installProPackageForUser(\App\Models\User $user): array
    {
        Log::channel('tunnels')->info("TunnelDeckService: Starting Pro package download and merge for user {$user->email}...");

        $zipContent = null;
        $source = 'licensing_api';

        // 1. Try downloading from licensing API endpoint
        $resp = $this->downloadProPackage($user->email);
        if ($resp && $resp->successful()) {
            $zipContent = $resp->body();
        }

        // 2. Local fallback if licensing server runs locally on the same host
        if (!$zipContent || strlen($zipContent) < 100) {
            $fallbackPaths = [
                'D:\\FINAL SAAS PROJECTS\\tunneldeck-licensing\\storage\\app\\private\\pro_plan_packages\\2\\domains-code.zip',
                base_path('../pro_packages/domains-code.zip'),
            ];
            foreach ($fallbackPaths as $path) {
                if (file_exists($path)) {
                    $zipContent = file_get_contents($path);
                    $source = 'local_fallback';
                    break;
                }
            }
        }

        if (!$zipContent) {
            return [
                'success' => false,
                'message' => 'Unable to download Pro package from licensing API.',
            ];
        }

        // 3. Extract and merge into project src
        $mergeResult = static::extractAndMergeProPackage($zipContent);

        // 4. Update local user package and license settings
        $user->package = 'pro';
        $user->license_synced_at = now();
        $user->save();

        \App\Models\Setting::set('license_tier', 'pro');

        \App\Models\Activity::create([
            'user_id' => $user->id,
            'description' => "TunnelDeck upgraded to Pro edition. " . ($mergeResult['files_count'] ?? 0) . " Pro codebase files merged into project src ({$source}).",
            'type' => 'success',
        ]);

        return array_merge($mergeResult, [
            'source' => $source,
            'package' => 'pro',
            'message' => 'TunnelDeck Lite successfully upgraded to Pro! Codebase files merged into project src.',
        ]);
    }
}

