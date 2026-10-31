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
        return Http::withToken($this->token)
            ->acceptJson()
            ->baseUrl($this->baseUrl)
            ->timeout(4);
    }

    /**
     * 1. Register a new user (Currency defaults to INR for India, USD for others. Package defaults to lite).
     */
    public function registerUser(array $data): ?Response
    {
        try {
            return $this->client()->post('/users/register', $data);
        } catch (\Throwable $e) {
            Log::error('TunnelDeckService registerUser error: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
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
     * 9. Synchronize user's license and package tier from cloud to local instance.
     */
    public function syncUserLicense(\App\Models\User $user): array
    {
        $response = $this->getUser($user->email);

        if (!$response || !$response->successful() || !$response->json('success')) {
            return [
                'success' => false,
                'message' => 'Unable to fetch license from licensing server.',
                'package' => $user->package,
            ];
        }

        $data = $response->json('data') ?? [];
        $cloudPackage = $data['package'] ?? 'lite';
        $cloudCurrency = $data['currency'] ?? null;
        $oldPackage = $user->package;

        $user->package = $cloudPackage;
        if ($cloudCurrency) {
            $user->currency = $cloudCurrency;
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
            'message' => "License synchronized successfully. Current plan: " . strtoupper($cloudPackage),
            'package' => $cloudPackage,
            'currency' => $cloudCurrency,
            'upgraded' => ($oldPackage !== $cloudPackage),
            'synced_at' => $user->license_synced_at->toIso8601String(),
            'data' => $data,
        ];
    }
}

