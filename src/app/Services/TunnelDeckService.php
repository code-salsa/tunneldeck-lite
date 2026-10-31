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
            ->timeout(10);
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
}
