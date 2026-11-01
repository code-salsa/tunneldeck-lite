<?php

namespace App\Services;

use App\Models\Tunnel;
use Illuminate\Support\Facades\Log;

class TunnelHealthService
{
    protected $processTracker;

    public function __construct(ProcessTracker $processTracker)
    {
        $this->processTracker = $processTracker;
    }

    /**
     * Check the health of a tunnel.
     * Returns: 'healthy', 'warning', or 'failed'
     */
    public function checkHealth(Tunnel $tunnel): string
    {
        if ($tunnel->is_dummy) {
            return $tunnel->status === 'running' ? 'healthy' : 'failed';
        }

        // If it's stopped, it shouldn't be running
        if ($tunnel->status === 'stopped') {
            return 'failed';
        }

        // 1. Check if PID exists and is active
        $isAlive = !empty($tunnel->pid) && $this->processTracker->isPidAlive($tunnel->pid);
        if (!$isAlive) {
            $realPid = $this->processTracker->findCloudflaredPidByPort($tunnel->port);
            if ($realPid) {
                Log::channel('tunnels')->info("TunnelHealthService: Stored PID is dead, but found active cloudflared process PID {$realPid} on port {$tunnel->port}. Updating database.");
                $tunnel->update(['pid' => $realPid]);
                $isAlive = true;
            }
        }

        if (!$isAlive) {
            Log::channel('tunnels')->warning("TunnelHealthService: Tunnel '{$tunnel->name}' (ID: {$tunnel->id}) has dead PID {$tunnel->pid}.");
            return 'failed';
        }

        // 2. Check if the local service port is responding
        $localPortActive = $this->isLocalPortResponding($tunnel->port);

        // 3. Verify public URL exists
        $hasPublicUrl = !empty($tunnel->public_url);

        if ($localPortActive && $hasPublicUrl) {
            return 'healthy';
        }

        // If cloudflared is running but local port is dead or URL is missing, return warning
        Log::channel('tunnels')->info("TunnelHealthService: Tunnel '{$tunnel->name}' (ID: {$tunnel->id}) warning. Port active: " . ($localPortActive ? 'YES' : 'NO') . ", Has public URL: " . ($hasPublicUrl ? 'YES' : 'NO'));
        return 'warning';
    }

    public static ?bool $forcePortRunning = null;

    /**
     * Test if a local TCP port is in running condition.
     */
    public function isPortRunning(int $port, float $timeout = 0.6): bool
    {
        if (static::$forcePortRunning !== null) {
            return static::$forcePortRunning;
        }

        if (app()->environment('testing') && static::$forcePortRunning === null) {
            return true;
        }

        return $this->isLocalPortResponding($port, $timeout);
    }

    /**
     * Test if a local TCP port is responding.
     */
    public function isLocalPortResponding(int $port, float $timeout = 0.6): bool
    {
        if (static::$forcePortRunning !== null) {
            return static::$forcePortRunning;
        }

        // 1. Try IPv4 127.0.0.1
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }

        // 2. Try IPv6 [::1]
        $connection6 = @fsockopen('[::1]', $port, $errno, $errstr, $timeout);
        if (is_resource($connection6)) {
            fclose($connection6);
            return true;
        }

        // 3. Try localhost
        $connectionHost = @fsockopen('localhost', $port, $errno, $errstr, $timeout);
        if (is_resource($connectionHost)) {
            fclose($connectionHost);
            return true;
        }

        return false;
    }
}
