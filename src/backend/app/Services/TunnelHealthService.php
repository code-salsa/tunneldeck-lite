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

    /**
     * Test if a local TCP port is responding.
     */
    protected function isLocalPortResponding(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }
}
