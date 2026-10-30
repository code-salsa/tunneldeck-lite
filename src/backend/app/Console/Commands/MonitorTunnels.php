<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tunnel;
use App\Services\TunnelManagerService;
use App\Services\TunnelHealthService;
use Illuminate\Support\Facades\Log;

class MonitorTunnels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tunnels:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor running cloudflared tunnels and restart failed ones if enabled';

    /**
     * Execute the console command.
     */
    public function handle(TunnelManagerService $manager, TunnelHealthService $healthService)
    {
        $this->info('Starting tunnels health check...');
        Log::channel('tunnels')->info('MonitorCommand: Running health check on all active tunnels...');

        $tunnels = Tunnel::whereIn('status', ['running', 'starting', 'error'])->get();

        if ($tunnels->isEmpty()) {
            $this->info('No active tunnels to monitor.');
            return 0;
        }

        foreach ($tunnels as $tunnel) {
            $health = $healthService->checkHealth($tunnel);

            if ($health === 'failed') {
                $this->warn("Tunnel '{$tunnel->name}' (ID: {$tunnel->id}) has FAILED.");
                
                // If it was supposed to be running, log crash and handle auto-restart
                if ($tunnel->status === 'running') {
                    Log::channel('tunnels')->error("MonitorCommand: Tunnel '{$tunnel->name}' crashed! Current state in DB is running, but health check failed.");
                    
                    // Mark as error
                    $tunnel->update([
                        'status' => 'error',
                        'public_url' => null,
                        'pid' => null,
                    ]);
                }

                if (config('tunneldeck.auto_restart', true)) {
                    $this->info("Attempting to auto-restart '{$tunnel->name}'...");
                    Log::channel('tunnels')->info("MonitorCommand: Auto-restarting tunnel '{$tunnel->name}' (ID: {$tunnel->id})...");
                    
                    $restarted = $manager->startTunnel($tunnel);
                    
                    if ($restarted) {
                        $this->info("Successfully restarted '{$tunnel->name}'.");
                        Log::channel('tunnels')->info("MonitorCommand: Auto-restart success for '{$tunnel->name}'.");
                    } else {
                        $this->error("Auto-restart failed for '{$tunnel->name}'.");
                        Log::channel('tunnels')->error("MonitorCommand: Auto-restart failed for '{$tunnel->name}'.");
                    }
                }
            } else {
                $this->info("Tunnel '{$tunnel->name}' is {$health}.");
            }
        }

        $this->info('Health check completed.');
        return 0;
    }
}
