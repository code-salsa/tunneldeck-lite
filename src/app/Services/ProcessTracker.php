<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class ProcessTracker
{
    /**
     * Check if a process with the given PID is active and running.
     */
    public function isPidAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $process = new Process(['tasklist', '/NH', '/FI', "PID eq {$pid}"]);
                $process->run();
                $output = $process->getOutput();
                return !empty($output) && stripos($output, 'No tasks are running') === false && strpos($output, (string)$pid) !== false;
            } catch (\Exception $e) {
                Log::channel('tunnels')->error("ProcessTracker: Error checking Windows PID {$pid} health: " . $e->getMessage());
                return false;
            }
        }

        // Try using posix extension if available
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        // Fallback to checking via ps command safely
        try {
            $process = new Process(['ps', '-p', $pid]);
            $process->run();
            
            // Exit code 0 means the process is running
            return $process->isSuccessful();
        } catch (\Exception $e) {
            Log::channel('tunnels')->error("ProcessTracker: Error checking PID {$pid} health: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Terminate a process safely by PID (SIGTERM, fallback to SIGKILL).
     */
    public function killProcess(int $pid): bool
    {
        if ($pid <= 0 || !$this->isPidAlive($pid)) {
            return true;
        }

        Log::channel('tunnels')->info("ProcessTracker: Terminating process PID {$pid}...");

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                // Kill process and all its children tree (/T)
                $process = new Process(['taskkill', '/F', '/T', '/PID', $pid]);
                $process->run();
                return $process->isSuccessful();
            } catch (\Exception $e) {
                Log::channel('tunnels')->error("ProcessTracker: Error killing Windows process PID {$pid}: " . $e->getMessage());
                return false;
            }
        }

        // Try SIGTERM (15) first
        if (function_exists('posix_kill')) {
            posix_kill($pid, 15); // SIGTERM
        } else {
            $process = new Process(['kill', '-15', $pid]);
            $process->run();
        }

        // Wait up to 3 seconds for it to exit
        for ($i = 0; $i < 30; $i++) {
            usleep(100000); // 100ms
            if (!$this->isPidAlive($pid)) {
                Log::channel('tunnels')->info("ProcessTracker: Process PID {$pid} terminated cleanly via SIGTERM.");
                return true;
            }
        }

        // Force SIGKILL (9) if still alive
        Log::channel('tunnels')->warning("ProcessTracker: Process PID {$pid} did not exit. Sending SIGKILL...");
        
        if (function_exists('posix_kill')) {
            posix_kill($pid, 9); // SIGKILL
        } else {
            $process = new Process(['kill', '-9', $pid]);
            $process->run();
        }

        // Final check
        usleep(100000); // 100ms
        $alive = $this->isPidAlive($pid);
        
        if (!$alive) {
            Log::channel('tunnels')->info("ProcessTracker: Process PID {$pid} killed successfully via SIGKILL.");
            return true;
        }

        Log::channel('tunnels')->error("ProcessTracker: Failed to terminate process PID {$pid}!");
        return false;
    }

    /**
     * Terminate any running cloudflared processes associated with a specific port.
     */
    public function killProcessesByPort(int $port): void
    {
        if ($port <= 0) {
            return;
        }

        Log::channel('tunnels')->info("ProcessTracker: Checking for running cloudflared processes on port {$port}...");

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $cmd = "powershell -Command \"Get-WmiObject Win32_Process -Filter \\\"name = 'cloudflared.exe'\\\" | Where-Object { \$_.CommandLine -like '*$port*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }\"";
                shell_exec($cmd);
            } catch (\Exception $e) {
                Log::channel('tunnels')->error("ProcessTracker: Error killing Windows cloudflared processes on port {$port}: " . $e->getMessage());
            }
            return;
        }

        // macOS / Linux
        try {
            $output = [];
            exec("ps aux | grep cloudflared", $output);
            foreach ($output as $line) {
                if (strpos($line, 'grep') !== false) {
                    continue;
                }
                
                // Match port pattern in command line
                if (strpos($line, "127.0.0.1:{$port}") !== false || strpos($line, "localhost:{$port}") !== false || strpos($line, ":{$port} ") !== false || preg_match("/:\b{$port}\b/", $line)) {
                    $tokens = preg_split('/\s+/', trim($line));
                    if (count($tokens) > 1) {
                        $pid = (int)$tokens[1];
                        if ($pid > 0) {
                            Log::channel('tunnels')->info("ProcessTracker: Found orphaned cloudflared process PID {$pid} on port {$port}. Terminating...");
                            $this->killProcess($pid);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::channel('tunnels')->error("ProcessTracker: Error searching/killing processes on port {$port}: " . $e->getMessage());
        }
    }

    /**
     * Find and terminate any running cloudflared processes spawned by TunnelDeck
     * that are no longer associated with an active tunnel in the database.
     */
    public function killOrphanedProcesses(): void
    {
        $activePids = \App\Models\Tunnel::whereIn('status', ['running', 'starting'])
            ->whereNotNull('pid')
            ->pluck('pid')
            ->toArray();

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $pidsString = implode(',', $activePids) ?: '0';
                $cmd = "powershell -Command \"Get-WmiObject Win32_Process -Filter \\\"name = 'cloudflared.exe'\\\" | Where-Object { (\$_.CommandLine -like '*tunneldeck*' -or \$_.CommandLine -like '*127.0.0.1:*') -and \$pidsString -notcontains \$_.ProcessId } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }\"";
                shell_exec($cmd);
            } catch (\Exception $e) {
                Log::channel('tunnels')->error("ProcessTracker: Error killing Windows orphaned processes: " . $e->getMessage());
            }
            return;
        }

        // macOS / Linux
        try {
            $output = [];
            exec("ps aux | grep cloudflared", $output);
            foreach ($output as $line) {
                if (strpos($line, 'grep') !== false) {
                    continue;
                }

                if (strpos($line, 'tunneldeck') !== false || strpos($line, '127.0.0.1:') !== false || strpos($line, 'localhost:') !== false) {
                    $tokens = preg_split('/\s+/', trim($line));
                    if (count($tokens) > 1) {
                        $pid = (int)$tokens[1];
                        if ($pid > 0 && !in_array($pid, $activePids)) {
                            Log::channel('tunnels')->info("ProcessTracker: Killing orphaned cloudflared process PID {$pid}: {$line}");
                            $this->killProcess($pid);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::channel('tunnels')->error("ProcessTracker: Error killing macOS/Linux orphaned processes: " . $e->getMessage());
        }
    }

    /**
     * Find the PID of a running cloudflared process associated with a specific port.
     */
    public function findCloudflaredPidByPort(int $port): ?int
    {
        if ($port <= 0) {
            return null;
        }

        // Get process UUID from database to match the config YAML filename in the process command line
        $tunnel = \App\Models\Tunnel::where('port', $port)->first();
        $uuid = $tunnel ? $tunnel->process_uuid : null;

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                // Query CIM/WMI for cloudflared.exe processes containing either the port or the config UUID in their command line
                if ($uuid) {
                    $cmd = "powershell -NoProfile -Command \"Get-CimInstance Win32_Process -Filter \\\"name = 'cloudflared.exe'\\\" | Where-Object { \$_.CommandLine -like '*$port*' -or \$_.CommandLine -like '*$uuid*' } | Select-Object -ExpandProperty ProcessId\"";
                } else {
                    $cmd = "powershell -NoProfile -Command \"Get-CimInstance Win32_Process -Filter \\\"name = 'cloudflared.exe'\\\" | Where-Object { \$_.CommandLine -like '*$port*' } | Select-Object -ExpandProperty ProcessId\"";
                }
                $output = shell_exec($cmd);
                if (!empty($output)) {
                    $pid = (int)trim(explode("\n", trim($output))[0]);
                    if ($pid > 0) {
                        return $pid;
                    }
                }
            } catch (\Exception $e) {
                Log::channel('tunnels')->error("ProcessTracker: Error finding Windows cloudflared PID on port {$port}: " . $e->getMessage());
            }
            return null;
        }

        // macOS / Linux
        try {
            $output = [];
            exec("ps aux | grep cloudflared", $output);
            foreach ($output as $line) {
                if (strpos($line, 'grep') !== false) {
                    continue;
                }
                
                $match = false;
                if ($uuid && strpos($line, $uuid) !== false) {
                    $match = true;
                } elseif (strpos($line, "127.0.0.1:{$port}") !== false || strpos($line, "localhost:{$port}") !== false || strpos($line, ":{$port} ") !== false || preg_match("/:\b{$port}\b/", $line)) {
                    $match = true;
                }

                if ($match) {
                    $tokens = preg_split('/\s+/', trim($line));
                    if (count($tokens) > 1) {
                        $pid = (int)$tokens[1];
                        if ($pid > 0) {
                            return $pid;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::channel('tunnels')->error("ProcessTracker: Error searching PID on port {$port}: " . $e->getMessage());
        }

        return null;
    }
}
