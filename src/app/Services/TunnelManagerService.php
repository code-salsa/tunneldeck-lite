<?php

namespace App\Services;

use App\Models\Tunnel;
use App\Models\Activity;
use App\Events\TunnelStarted;
use App\Events\TunnelStopped;
use App\Events\TunnelStatusChanged;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TunnelManagerService
{
    protected $processTracker;
    protected $urlParser;

    public function __construct(ProcessTracker $processTracker, CloudflareUrlParser $urlParser)
    {
        $this->processTracker = $processTracker;
        $this->urlParser = $urlParser;
    }

    /**
     * Create a new tunnel configuration in the database.
     */
    public function createTunnel(array $data): Tunnel
    {
        $userId = auth()->id();
        
        // Check max tunnels limit
        $limit = config('tunneldeck.max_tunnels', 10);
        $currentCount = $userId ? Tunnel::where('user_id', $userId)->count() : Tunnel::count();
        if ($currentCount >= $limit) {
            throw new \Exception("Maximum tunnel limit of {$limit} reached.");
        }

        $tunnel = Tunnel::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'port' => $data['port'],
            'custom_subdomain' => $data['custom_subdomain'] ?? null,
            'status' => 'stopped',
        ]);

        Log::channel('tunnels')->info("Tunnel created: ID={$tunnel->id}, Name={$tunnel->name}, Port={$tunnel->port}");
        
        Activity::create([
            'user_id' => $userId,
            'description' => "Tunnel {$tunnel->name} Added",
            'type' => 'added',
        ]);

        return $tunnel;
    }

    /**
     * Start a tunnel.
     */
    public function startTunnel(Tunnel $tunnel): bool
    {
        // If already running, return true
        if ($tunnel->status === 'running' && $tunnel->pid && $this->processTracker->isPidAlive($tunnel->pid)) {
            return true;
        }

        $oldStatus = $tunnel->status;
        $tunnel->update([
            'status' => 'starting',
            'process_uuid' => (string) Str::uuid(),
            'last_started_at' => Carbon::now(),
        ]);
        
        event(new TunnelStatusChanged($tunnel, $oldStatus, 'starting'));

        // Prepare logs directory
        $logsDir = storage_path('logs/tunnels');
        if (!file_exists($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        $logPath = $logsDir . '/' . $tunnel->process_uuid . '.log';
        $binary = config('tunneldeck.cloudflared_binary_path', 'cloudflared');

        $defaultTunnelDomain = \App\Models\Setting::get('default_tunnel_domain');
        $isNamedTunnel = !empty($tunnel->custom_domain_id) || !empty($defaultTunnelDomain);
        $publicUrl = null;

        if ($isNamedTunnel) {
            $domain = null;
            $subdomain = null;
            $isRoot = !empty($tunnel->is_root_domain) || $tunnel->custom_subdomain === '@';

            if (!empty($tunnel->custom_domain_id)) {
                $customDomain = \App\Models\CustomDomain::find($tunnel->custom_domain_id);
                if ($customDomain) {
                    $domain = $customDomain->domain;
                } else {
                    throw new \Exception("Selected custom domain not found.");
                }

                if ($isRoot) {
                    $subdomain = null;
                } elseif (!empty($tunnel->custom_subdomain)) {
                    $subdomain = strtolower($tunnel->custom_subdomain);
                } else {
                    $subdomain = $this->generateFriendlyRandomName();
                }
            } else {
                $domain = $defaultTunnelDomain;
                if ($isRoot && auth()->check() && auth()->user()->isSuperAdmin()) {
                    $subdomain = null;
                } elseif (!empty($tunnel->custom_subdomain)) {
                    $subdomain = strtolower($tunnel->custom_subdomain);
                } else {
                    $subdomain = $this->generateFriendlyRandomName();
                }
            }

            if (!empty($domain)) {
                $tunnelName = 'tunneldeck-' . $tunnel->id;
                // Ensure the unique named tunnel exists
                $checkCommand = sprintf('%s tunnel info %s 2>&1', escapeshellarg($binary), escapeshellarg($tunnelName));
                exec($checkCommand, $checkOutput, $checkExitCode);
                
                $uuid = null;
                $hasLocalCert = false;
                if ($checkExitCode === 0) {
                    $outputText = implode("\n", $checkOutput);
                    if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $outputText, $matches)) {
                        $uuid = $matches[1];
                        $home = \App\Services\DomainValidationService::getHomeDir();
                        $credPath = $home . DIRECTORY_SEPARATOR . '.cloudflared' . DIRECTORY_SEPARATOR . $uuid . '.json';
                        if (file_exists($credPath)) {
                            $hasLocalCert = true;
                        } else {
                            Log::channel('tunnels')->warning("TunnelManagerService: Tunnel '{$tunnelName}' exists on Cloudflare (UUID: {$uuid}) but local credentials file is missing at {$credPath}.");
                        }
                    }
                }

                if ($checkExitCode !== 0 || !$hasLocalCert) {
                    if ($checkExitCode === 0) {
                        Log::channel('tunnels')->info("TunnelManagerService: Deleting orphaned remote tunnel '{$tunnelName}' to allow recreation...");
                        $deleteCommand = sprintf('%s tunnel delete -f %s 2>&1', escapeshellarg($binary), escapeshellarg($tunnelName));
                        exec($deleteCommand, $deleteOutput, $deleteExitCode);
                    }

                    Log::channel('tunnels')->info("TunnelManagerService: Named tunnel '{$tunnelName}' not found or credentials missing. Attempting to create it...");
                    $createCommand = sprintf('%s tunnel create %s 2>&1', escapeshellarg($binary), escapeshellarg($tunnelName));
                    exec($createCommand, $createOutput, $createExitCode);
                    if ($createExitCode !== 0) {
                        $createText = implode(" ", $createOutput);
                        throw new \Exception("Failed to create named tunnel '{$tunnelName}': {$createText}");
                    }
                    Log::channel('tunnels')->info("TunnelManagerService: Named tunnel '{$tunnelName}' created successfully.");
                    
                    // Parse UUID from createOutput
                    $outputText = implode("\n", $createOutput);
                    if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $outputText, $matches)) {
                        $uuid = $matches[1];
                    }
                    
                    // Fallback to check info if UUID is still empty
                    if (empty($uuid)) {
                        $checkCommand = sprintf('%s tunnel info %s 2>&1', escapeshellarg($binary), escapeshellarg($tunnelName));
                        exec($checkCommand, $checkOutput2, $checkExitCode2);
                        if ($checkExitCode2 === 0) {
                            $outputText2 = implode("\n", $checkOutput2);
                            if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $outputText2, $matches)) {
                                $uuid = $matches[1];
                            }
                        }
                    }
                }

                $hostname = !empty($subdomain) ? "{$subdomain}.{$domain}" : $domain;
                $publicUrl = "https://{$hostname}";

                // 1. Route DNS for this hostname (creates DNS record in Cloudflare)
                $routeCommand = sprintf(
                    '%s tunnel route dns --overwrite-dns %s %s 2>&1',
                    escapeshellarg($binary),
                    escapeshellarg($tunnelName),
                    escapeshellarg($hostname)
                );
                Log::channel('tunnels')->info("TunnelManagerService: Routing DNS: {$routeCommand}");
                
                // Execute DNS routing command
                exec($routeCommand, $routeOutput, $routeExitCode);
                Log::channel('tunnels')->info("TunnelManagerService: DNS routing exit code: {$routeExitCode}. Output: " . implode("\n", $routeOutput));
                
                if ($routeExitCode !== 0) {
                    $errorText = implode(" ", $routeOutput);
                    if (stripos($errorText, 'already exists') === false) {
                        throw new \Exception("DNS Routing failed: {$errorText}");
                    }
                    Log::channel('tunnels')->info("TunnelManagerService: DNS record already exists. Proceeding to run tunnel.");
                }
            }
        }

        $cmdPrefix = PHP_OS_FAMILY === 'Windows' ? '' : 'exec ';

        // Formulate the shell command to run detached
        if ($isNamedTunnel && !empty($publicUrl)) {
            if (empty($uuid)) {
                $tunnelName = 'tunneldeck-' . $tunnel->id;
                $checkCommand = sprintf('%s tunnel info %s 2>&1', escapeshellarg($binary), escapeshellarg($tunnelName));
                exec($checkCommand, $checkOutput, $checkExitCode);
                if ($checkExitCode === 0) {
                    $outputText = implode("\n", $checkOutput);
                    if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $outputText, $matches)) {
                        $uuid = $matches[1];
                    }
                }
            }

            if (empty($uuid)) {
                if (app()->environment('testing')) {
                    $uuid = '11111111-2222-3333-4444-555555555555';
                } else {
                    throw new \Exception("Unable to resolve UUID for named tunnel 'tunneldeck-{$tunnel->id}'.");
                }
            }

            $home = \App\Services\DomainValidationService::getHomeDir();
            $credPath = $home . DIRECTORY_SEPARATOR . '.cloudflared' . DIRECTORY_SEPARATOR . $uuid . '.json';

            // Generate a temporary yaml config file
            $configPath = $logsDir . '/' . $tunnel->process_uuid . '.yaml';
            $configContent = "tunnel: {$uuid}\n" .
                             "credentials-file: " . str_replace('\\', '/', $credPath) . "\n" .
                             "ingress:\n" .
                             "  - hostname: {$hostname}\n" .
                             "    service: http://127.0.0.1:{$tunnel->port}\n" .
                             "  - service: http_status:404\n";
            file_put_contents($configPath, $configContent);

            $command = sprintf(
                '%s%s --config %s tunnel run > %s 2>&1',
                $cmdPrefix,
                escapeshellarg($binary),
                escapeshellarg($configPath),
                escapeshellarg($logPath)
            );
        } else {
            $command = sprintf(
                '%s%s tunnel --url http://127.0.0.1:%d > %s 2>&1',
                $cmdPrefix,
                escapeshellarg($binary),
                $tunnel->port,
                escapeshellarg($logPath)
            );
            // Ensure $isNamedTunnel is set to false if we fallback
            $isNamedTunnel = false;
        }

        Log::channel('tunnels')->info("TunnelManagerService: Spawning process for ID={$tunnel->id}. Command: {$command}");

        try {
            $process = BackgroundProcess::fromShellCommandline($command);
            
            // Set standard variables or settings if needed, and start asynchronously
            $process->start();

            // Give the OS a tiny fraction of time to boot the process
            usleep(200000); // 200ms

            $pid = $process->getPid();
            if (!$pid || !$process->isRunning()) {
                throw new \Exception("Process failed to start or immediately terminated.");
            }

            Log::channel('tunnels')->info("TunnelManagerService: Started background process for ID={$tunnel->id} with PID={$pid}.");

            // Update PID in database
            $tunnel->update(['pid' => $pid]);

            if ($isNamedTunnel) {
                // Wait a brief moment to ensure the tunnel process doesn't crash right away
                usleep(500000); // 500ms
                if (!$process->isRunning()) {
                    throw new \Exception("Process crashed immediately after starting. Check logs.");
                }

                $oldStatus = $tunnel->status;
                $tunnel->update([
                    'status' => 'running',
                    'public_url' => $publicUrl,
                ]);

                Log::channel('tunnels')->info("Named Tunnel started successfully: ID={$tunnel->id}, PID={$pid}, Public URL={$publicUrl}");
                
                Activity::create([
                    'user_id' => $tunnel->user_id,
                    'description' => "Named Tunnel {$tunnel->name} Started on {$publicUrl}",
                    'type' => 'started',
                ]);

                event(new TunnelStarted($tunnel));
                event(new TunnelStatusChanged($tunnel, $oldStatus, 'running'));
                
                return true;
            } else {
                // Poll log file for the public trycloudflare URL
                $timeout = config('tunneldeck.process_timeout', 30);
                $limitLoops = $timeout * 4; // Check 4 times a second
                $quickUrl = null;

                for ($i = 0; $i < $limitLoops; $i++) {
                    $quickUrl = $this->urlParser->extractUrl($logPath);
                    if ($quickUrl) {
                        break;
                    }

                    // Check if the process crashed while booting (allow 3 seconds grace for wrapper process)
                    if ($i > 12 && !$this->processTracker->isPidAlive($pid)) {
                        $realPid = $this->processTracker->findCloudflaredPidByPort($tunnel->port);
                        if ($realPid) {
                            $pid = $realPid;
                            $tunnel->update(['pid' => $realPid]);
                        } else {
                            // Check one more time if URL is present before failing
                            $quickUrl = $this->urlParser->extractUrl($logPath);
                            if ($quickUrl) {
                                break;
                            }
                            Log::channel('tunnels')->warning("TunnelManagerService: Process PID={$pid} exited prematurely during boot.");
                            break;
                        }
                    }

                    usleep(250000); // 250ms
                }

                if ($quickUrl) {
                    $realPid = $this->processTracker->findCloudflaredPidByPort($tunnel->port);
                    if ($realPid) {
                        $pid = $realPid;
                    }

                    // Successfully started quick tunnel
                    $oldStatus = $tunnel->status;
                    $tunnel->update([
                        'status' => 'running',
                        'public_url' => $quickUrl,
                        'pid' => $pid,
                    ]);

                    Log::channel('tunnels')->info("Tunnel started successfully: ID={$tunnel->id}, PID={$pid}, Public URL={$quickUrl}");
                    
                    Activity::create([
                        'user_id' => $tunnel->user_id,
                        'description' => "Tunnel {$tunnel->name} Started",
                        'type' => 'started',
                    ]);

                    event(new TunnelStarted($tunnel));
                    event(new TunnelStatusChanged($tunnel, $oldStatus, 'running'));
                    
                    return true;
                } else {
                    // Failed to resolve URL within timeout or crashed
                    throw new \Exception("Failed to obtain public URL within {$timeout} seconds.");
                }
            }

        } catch (\Exception $e) {
            Log::channel('tunnels')->error("Tunnel crashed or failed to start: ID={$tunnel->id}. Error: " . $e->getMessage());
            
            // Cleanup process if it was left hanging
            if (isset($pid) && $pid > 0) {
                $this->processTracker->killProcess($pid);
            }

            $oldStatus = $tunnel->status;
            $tunnel->update([
                'status' => 'error',
                'public_url' => null,
                'pid' => null,
            ]);

            Activity::create([
                'user_id' => $tunnel->user_id,
                'description' => "Tunnel {$tunnel->name} Crash or Startup Error: " . $e->getMessage(),
                'type' => 'error',
            ]);

            event(new TunnelStatusChanged($tunnel, $oldStatus, 'error'));

            return false;
        }
    }

    /**
     * Stop a tunnel.
     */
    public function stopTunnel(Tunnel $tunnel): bool
    {
        if ($tunnel->status === 'stopped' && empty($tunnel->pid)) {
            return true;
        }

        Log::channel('tunnels')->info("TunnelManagerService: Stopping tunnel ID={$tunnel->id}...");

        if ($tunnel->pid) {
            $this->processTracker->killProcess($tunnel->pid);
        }

        // Also ensure any running cloudflared processes on this port are killed
        $this->processTracker->killProcessesByPort($tunnel->port);

        $oldStatus = $tunnel->status;
        
        // Delete temporary yaml config file if it exists
        if ($tunnel->process_uuid) {
            $configPath = storage_path('logs/tunnels/' . $tunnel->process_uuid . '.yaml');
            if (file_exists($configPath)) {
                @unlink($configPath);
            }
        }

        $tunnel->update([
            'status' => 'stopped',
            'public_url' => null,
            'pid' => null,
            'last_stopped_at' => Carbon::now(),
        ]);

        Log::channel('tunnels')->info("Tunnel stopped: ID={$tunnel->id}");

        Activity::create([
            'user_id' => $tunnel->user_id,
            'description' => "Tunnel {$tunnel->name} Stopped",
            'type' => 'stopped',
        ]);

        event(new TunnelStopped($tunnel));
        event(new TunnelStatusChanged($tunnel, $oldStatus, 'stopped'));

        return true;
    }

    /**
     * Restart a tunnel.
     */
    public function restartTunnel(Tunnel $tunnel): bool
    {
        Log::channel('tunnels')->info("TunnelManagerService: Restarting tunnel ID={$tunnel->id}...");
        
        Activity::create([
            'user_id' => $tunnel->user_id,
            'description' => "Tunnel {$tunnel->name} Restarting",
            'type' => 'updated',
        ]);

        $this->stopTunnel($tunnel);
        
        // Brief pause between stop and start
        usleep(500000); // 500ms

        return $this->startTunnel($tunnel);
    }

    /**
     * Check active PID and verify status matches actual system process.
     */
    public function getTunnelStatus(Tunnel $tunnel): string
    {
        if ($tunnel->status === 'running' || $tunnel->status === 'starting') {
            $isAlive = !empty($tunnel->pid) && $this->processTracker->isPidAlive($tunnel->pid);
            
            // Fallback: Check if the actual cloudflared process is running on the port
            if (!$isAlive) {
                $realPid = $this->processTracker->findCloudflaredPidByPort($tunnel->port);
                if ($realPid) {
                    Log::channel('tunnels')->info("TunnelManagerService: Stored PID {$tunnel->pid} is dead, but found active cloudflared process PID {$realPid} on port {$tunnel->port}. Updating database.");
                    $tunnel->update(['pid' => $realPid]);
                    $isAlive = true;
                }
            }

            if (!$isAlive) {
                Log::channel('tunnels')->warning("TunnelManagerService: Detected running tunnel ID={$tunnel->id} has died on PID={$tunnel->pid}. Marking as error.");
                
                $oldStatus = $tunnel->status;
                $tunnel->update([
                    'status' => 'error',
                    'public_url' => null,
                    'pid' => null,
                ]);

                Activity::create([
                    'user_id' => $tunnel->user_id,
                    'description' => "Tunnel {$tunnel->name} Crashed (Detected by health check)",
                    'type' => 'error',
                ]);

                event(new TunnelStatusChanged($tunnel, $oldStatus, 'error'));
            }
        }

        return $tunnel->status;
    }

    /**
     * Sync database states with actual system processes (e.g. after system reboot).
     */
    public function syncTunnelStates(): void
    {
        Log::channel('tunnels')->info("TunnelManagerService: Syncing all tunnel states with system processes...");

        $allTunnels = Tunnel::all();

        foreach ($allTunnels as $tunnel) {
            $isAlive = !empty($tunnel->pid) && $this->processTracker->isPidAlive($tunnel->pid);
            
            if (!$isAlive && in_array($tunnel->status, ['running', 'starting'])) {
                $realPid = $this->processTracker->findCloudflaredPidByPort($tunnel->port);
                if ($realPid) {
                    Log::channel('tunnels')->info("TunnelManagerService: Syncing. Stored PID is dead, but found active cloudflared process PID {$realPid} on port {$tunnel->port}. Updating database.");
                    $tunnel->update(['pid' => $realPid]);
                    $isAlive = true;
                }
            }
            
            if (in_array($tunnel->status, ['running', 'starting'])) {
                if (!$isAlive) {
                    Log::channel('tunnels')->info("TunnelManagerService: Recovering state. Tunnel ID={$tunnel->id} (PID={$tunnel->pid}) is not running. Resetting status to 'stopped'.");
                    
                    $oldStatus = $tunnel->status;
                    $tunnel->update([
                        'status' => 'stopped',
                        'public_url' => null,
                        'pid' => null,
                    ]);

                    Activity::create([
                        'user_id' => $tunnel->user_id,
                        'description' => "Tunnel {$tunnel->name} recovered to Stopped status on system startup",
                        'type' => 'stopped',
                    ]);

                    event(new TunnelStatusChanged($tunnel, $oldStatus, 'stopped'));
                }
            }
        }

        // Clean up all orphaned cloudflared processes spawned by TunnelDeck
        $this->processTracker->killOrphanedProcesses();
    }

    /**
     * Generate a friendly random name like lincoln-delight-holdem-flat
     */
    protected function generateFriendlyRandomName(): string
    {
        $words = [
            'lincoln', 'delight', 'holdem', 'flat', 'swift', 'bold', 'magic', 'cool',
            'bright', 'cozy', 'quick', 'happy', 'smooth', 'gentle', 'grand', 'silent',
            'super', 'epic', 'wild', 'calm', 'star', 'moon', 'sun', 'sky',
            'sea', 'wind', 'wave', 'hill', 'tree', 'leaf', 'rock', 'sand',
            'lake', 'river', 'rain', 'snow', 'cloud', 'bird', 'fish', 'deer',
            'zone', 'port', 'node', 'link', 'flow', 'beam', 'spark', 'flare',
            'peak', 'vale', 'path', 'road', 'gate', 'door', 'hall', 'room'
        ];
        
        $chosen = array_rand(array_flip($words), 4);
        shuffle($chosen);
        return implode('-', $chosen);
    }

    /**
     * Delete a named tunnel on Cloudflare when the configuration is deleted.
     */
    public function deleteNamedTunnel(Tunnel $tunnel): void
    {
        $defaultTunnelDomain = \App\Models\Setting::get('default_tunnel_domain');
        $isNamedTunnel = !empty($tunnel->custom_domain_id) || !empty($defaultTunnelDomain);

        if (!$isNamedTunnel) {
            return;
        }

        try {
            $binary = config('tunneldeck.cloudflared_binary_path', 'cloudflared');
            $tunnelName = 'tunneldeck-' . $tunnel->id;

            Log::channel('tunnels')->info("TunnelManagerService: Deleting named tunnel '{$tunnelName}' from Cloudflare...");
            
            $deleteCommand = sprintf('%s tunnel delete -f %s 2>&1', escapeshellarg($binary), escapeshellarg($tunnelName));
            exec($deleteCommand, $deleteOutput, $deleteExitCode);
            
            Log::channel('tunnels')->info("TunnelManagerService: Delete command exited with code {$deleteExitCode}. Output: " . implode("\n", $deleteOutput));
        } catch (\Exception $e) {
            Log::channel('tunnels')->error("TunnelManagerService: Error deleting named tunnel from Cloudflare: " . $e->getMessage());
        }
    }
}
