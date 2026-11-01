<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TunnelStoreRequest;
use App\Http\Requests\TunnelUpdateRequest;
use App\Models\Activity;
use App\Models\Tunnel;
use App\Services\TunnelHealthService;
use App\Services\TunnelManagerService;
use App\Services\TunnelDeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TunnelController extends Controller
{
    protected TunnelManagerService $manager;
    protected TunnelHealthService $healthService;
    protected TunnelDeckService $tunnelDeck;

    public function __construct(
        TunnelManagerService $manager,
        TunnelHealthService $healthService,
        TunnelDeckService $tunnelDeck
    ) {
        $this->manager = $manager;
        $this->healthService = $healthService;
        $this->tunnelDeck = $tunnelDeck;
    }

    /**
     * Get tunnel limit status and package allowance from API.
     */
    public function limit(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user() ?? \App\Models\User::first();
        $limitInfo = $user ? $this->tunnelDeck->getTunnelLimit($user) : [
            'max_tunnels' => 5,
            'is_unlimited' => false,
            'total_created' => Tunnel::count(),
            'remaining' => max(0, 5 - Tunnel::count()),
            'limit_reached' => Tunnel::count() >= 5,
            'package' => 'lite',
            'message' => Tunnel::count() >= 5 ? 'You have reached the number of allowed tunnel creation. Upgrade to a pro plan to enjoy unlimited tunnels.' : null,
        ];

        return response()->json([
            'status' => 'success',
            'data' => $limitInfo,
        ]);
    }

    /**
     * Check if a local port is in running condition.
     */
    public function checkPort(int $port): JsonResponse
    {
        if ($port < 1 || $port > 65535) {
            return response()->json([
                'status' => 'error',
                'running' => false,
                'port' => $port,
                'message' => 'Port number must be between 1 and 65535.',
            ], 422);
        }

        $isRunning = $this->healthService->isPortRunning($port);

        return response()->json([
            'status' => 'success',
            'running' => $isRunning,
            'port' => $port,
            'message' => $isRunning
                ? "Port {$port} is in running condition."
                : "Port {$port} is not in running condition. No service listening on 127.0.0.1:{$port}.",
        ]);
    }

    /**
     * List all tunnels with live health and statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $query = Tunnel::query();

        if ($userId) {
            $query->where('user_id', $userId)->orWhereNull('user_id');
        }

        $tunnels = $query->orderBy('created_at', 'desc')->get()->map(function ($tunnel) {
            $tunnel->health = $this->healthService->checkHealth($tunnel);
            if ($tunnel->is_dummy) {
                if ($tunnel->status === 'running') {
                    $elapsed = $tunnel->last_started_at ? abs((int) now()->diffInSeconds($tunnel->last_started_at)) : 0;
                    $remaining = max(0, 60 - $elapsed);
                    if ($remaining <= 0) {
                        Tunnel::where('id', $tunnel->id)->update(['status' => 'stopped', 'public_url' => null]);
                        $tunnel->status = 'stopped';
                        $tunnel->public_url = null;
                        $remaining = 0;
                    }
                    $tunnel->demo_remaining_seconds = $remaining;
                } else {
                    $tunnel->demo_remaining_seconds = 60;
                }
            }
            return $tunnel;
        });

        $totalTunnels = $tunnels->count();
        $runningTunnels = $tunnels->where('status', 'running')->count();
        $stoppedTunnels = $tunnels->where('status', 'stopped')->count();

        /** @var \App\Models\User $user */
        $user = auth()->user() ?? \App\Models\User::first();
        $limitInfo = $user ? $this->tunnelDeck->getTunnelLimit($user) : [
            'max_tunnels' => 5,
            'is_unlimited' => false,
            'total_created' => $totalTunnels,
            'remaining' => max(0, 5 - $totalTunnels),
            'limit_reached' => $totalTunnels >= 5,
            'package' => 'lite',
            'message' => $totalTunnels >= 5 ? 'You have reached the number of allowed tunnel creation. Upgrade to a pro plan to enjoy unlimited tunnels.' : null,
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'tunnels' => $tunnels,
                'metrics' => [
                    'total' => $totalTunnels,
                    'active' => $runningTunnels,
                    'paused' => $stoppedTunnels,
                    'total_requests' => $tunnels->sum('connections_count'),
                ],
                'limit' => $limitInfo,
            ],
        ]);
    }

    /**
     * Get dashboard stats and recent activities.
     */
    public function stats(): JsonResponse
    {
        $userId = auth()->id();
        $tunnels = Tunnel::when($userId, fn ($q) => $q->where('user_id', $userId))->get();

        $running = 0;
        $stopped = 0;
        foreach ($tunnels as $tunnel) {
            $status = $this->manager->getTunnelStatus($tunnel);
            if ($status === 'running') {
                $running++;
            } else {
                $stopped++;
            }
        }

        $lastStarted = Tunnel::when($userId, fn ($q) => $q->where('user_id', $userId))
            ->where('status', 'running')
            ->orderBy('last_started_at', 'desc')
            ->first();

        $activities = Activity::when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'total' => $tunnels->count(),
            'running' => $running,
            'stopped' => $stopped,
            'last_started' => $lastStarted ? $lastStarted->name : 'None',
            'activities' => $activities,
        ]);
    }

    /**
     * Store a new tunnel configuration.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user() ?? \App\Models\User::first();
        if ($user) {
            $limitInfo = $this->tunnelDeck->getTunnelLimit($user);
            if ($limitInfo['limit_reached']) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'TUNNEL_LIMIT_REACHED',
                    'message' => 'You have reached the number of allowed tunnel creation. Upgrade to a pro plan to enjoy unlimited tunnels.',
                    'limit' => $limitInfo,
                ], 403);
            }
        }

        $rawName = trim((string) $request->input('name', ''));
        $rawPort = $request->input('port');

        // Golden Rule 1: Tunnel name should be unique
        if (!empty($rawName) && Tunnel::whereRaw('LOWER(name) = ?', [strtolower($rawName)])->exists()) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'TUNNEL_NAME_TAKEN',
                'field' => 'name',
                'name' => $rawName,
                'message' => "The tunnel name '{$rawName}' has already been taken. Tunnel name must be unique.",
                'errors' => [
                    'name' => ["The name has already been taken."],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tunnels,name'],
            'port' => ['required', 'integer', 'min:1', 'max:65535', 'unique:tunnels,port'],
            'custom_subdomain' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9-]+$/'],
        ]);

        $port = (int) $validated['port'];

        // Golden Rule 2: The port should verify to be in running condition
        if (!$this->healthService->isPortRunning($port)) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'PORT_NOT_RUNNING',
                'field' => 'port',
                'port' => $port,
                'message' => "Port {$port} is not in running condition. Ensure your local service is active and listening on port {$port} before creating a tunnel.",
                'errors' => [
                    'port' => ["Port {$port} is not in running condition. Ensure your local application or dev server is running on port {$port}."],
                ],
            ], 422);
        }

        try {
            $tunnel = $this->manager->createTunnel([
                'name' => $validated['name'],
                'port' => (int) $validated['port'],
                'custom_subdomain' => $validated['custom_subdomain'] ?? null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Tunnel configuration created successfully.',
                'tunnel' => $tunnel,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to create tunnel: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Start a tunnel process (launches cloudflared and resolves public URL).
     */
    public function start(Request $request, Tunnel $tunnel): JsonResponse
    {
        // For exploration mode dummy tunnels, simulate starting without executing OS processes
        if ($tunnel->is_dummy) {
            $tunnel->status = 'running';
            $tunnel->last_started_at = now();
            if (empty($tunnel->public_url)) {
                $sub = $tunnel->custom_subdomain ?: \Illuminate\Support\Str::slug($tunnel->name);
                $tunnel->public_url = "https://{$sub}.trycloudflare.com";
            }
            $tunnel->save();
            $tunnel->demo_remaining_seconds = 60;

            return response()->json([
                'status' => 'success',
                'is_dummy' => true,
                'demo_remaining_seconds' => 60,
                'message' => 'Demo tunnel started in simulation mode.',
                'tunnel' => $tunnel,
                'simulation_steps' => [
                    ['step' => 1, 'title' => 'Verifying local listener port ' . $tunnel->port, 'status' => 'success', 'desc' => 'Port listener active (Simulated)'],
                    ['step' => 2, 'title' => 'Establishing Cloudflare Edge connection', 'status' => 'success', 'desc' => 'Connected to nearest edge protocol'],
                    ['step' => 3, 'title' => 'Generating Zero-Trust TLS Certificate', 'status' => 'success', 'desc' => 'Certificate issued & active'],
                    ['step' => 4, 'title' => 'Assigning public trycloudflare URL', 'status' => 'success', 'desc' => $tunnel->public_url],
                ],
            ]);
        }

        if ($tunnel->status === 'running' && $tunnel->isRunning()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tunnel is already running.',
                'tunnel' => $tunnel,
            ]);
        }

        // Apply any routing strategy parameters sent from the Start Tunnel Routing modal
        $needsSave = false;
        if ($request->has('custom_domain_id')) {
            $val = $request->input('custom_domain_id');
            $tunnel->custom_domain_id = ($val && $val !== 'default') ? (int) $val : null;
            $needsSave = true;
        }
        if ($request->has('is_root_domain')) {
            $tunnel->is_root_domain = (bool) $request->input('is_root_domain');
            $needsSave = true;
        }
        if ($request->has('custom_subdomain')) {
            $sub = $request->input('custom_subdomain');
            $tunnel->custom_subdomain = $sub ? strtolower(trim($sub)) : null;
            $needsSave = true;
        }
        if ($needsSave) {
            $tunnel->save();
        }

        try {
            $started = $this->manager->startTunnel($tunnel);
            $tunnel->refresh();

            if ($started) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Tunnel started successfully.',
                    'tunnel' => $tunnel,
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to start tunnel process. Check cloudflared configuration.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error starting tunnel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stop a tunnel process.
     */
    public function stop(Request $request, Tunnel $tunnel): JsonResponse
    {
        if ($tunnel->is_dummy) {
            $tunnel->status = 'stopped';
            $tunnel->last_stopped_at = now();
            $tunnel->save();

            return response()->json([
                'status' => 'success',
                'is_dummy' => true,
                'message' => 'Demo tunnel stopped.',
                'tunnel' => $tunnel,
            ]);
        }

        $stopped = $this->manager->stopTunnel($tunnel);
        $tunnel->refresh();

        return response()->json([
            'status' => $stopped ? 'success' : 'error',
            'message' => $stopped ? 'Tunnel stopped successfully.' : 'Unable to stop tunnel.',
            'tunnel' => $tunnel,
        ]);
    }

    /**
     * Restart a tunnel.
     */
    public function restart(Request $request, Tunnel $tunnel): JsonResponse
    {
        $restarted = $this->manager->restartTunnel($tunnel);
        $tunnel->refresh();

        return response()->json([
            'status' => $restarted ? 'success' : 'error',
            'message' => $restarted ? 'Tunnel restarted successfully.' : 'Unable to restart tunnel.',
            'tunnel' => $tunnel,
        ]);
    }

    /**
     * Toggle status (start if stopped, stop if running).
     */
    public function toggleStatus(Tunnel $tunnel): JsonResponse
    {
        if ($tunnel->status === 'running') {
            return $this->stop(request(), $tunnel);
        }
        return $this->start(request(), $tunnel);
    }

    /**
     * Get live status and health of a tunnel.
     */
    public function status(Request $request, Tunnel $tunnel): JsonResponse
    {
        $status = $this->manager->getTunnelStatus($tunnel);
        $health = $this->healthService->checkHealth($tunnel);

        return response()->json([
            'status' => 'success',
            'tunnel_status' => $status,
            'health' => $health,
            'public_url' => $tunnel->public_url,
        ]);
    }

    /**
     * Update an existing tunnel configuration.
     */
    public function update(Request $request, Tunnel $tunnel): JsonResponse
    {
        $rawName = trim((string) $request->input('name', ''));
        if (!empty($rawName) && Tunnel::where('id', '!=', $tunnel->id)->whereRaw('LOWER(name) = ?', [strtolower($rawName)])->exists()) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'TUNNEL_NAME_TAKEN',
                'field' => 'name',
                'name' => $rawName,
                'message' => "The tunnel name '{$rawName}' has already been taken. Tunnel name must be unique.",
                'errors' => [
                    'name' => ["The name has already been taken."],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tunnels,name,' . $tunnel->id],
            'port' => ['required', 'integer', 'min:1', 'max:65535', 'unique:tunnels,port,' . $tunnel->id],
        ]);

        $port = (int) $validated['port'];

        if (!$this->healthService->isPortRunning($port)) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'PORT_NOT_RUNNING',
                'field' => 'port',
                'port' => $port,
                'message' => "Port {$port} is not in running condition. Ensure your local service is active and listening on port {$port} before updating the tunnel.",
                'errors' => [
                    'port' => ["Port {$port} is not in running condition. Ensure your local application or dev server is running on port {$port}."],
                ],
            ], 422);
        }

        $tunnel->update([
            'name' => $validated['name'],
            'port' => (int) $validated['port'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tunnel updated successfully.',
            'tunnel' => $tunnel,
        ]);
    }

    /**
     * Delete a tunnel.
     */
    public function destroy(Tunnel $tunnel): JsonResponse
    {
        $this->manager->stopTunnel($tunnel);
        $this->manager->deleteNamedTunnel($tunnel);

        $name = $tunnel->name;
        $tunnel->delete();

        Activity::create([
            'user_id' => auth()->id(),
            'description' => "Tunnel {$name} Deleted",
            'type' => 'deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tunnel deleted successfully.',
        ]);
    }

    /**
     * Bulk delete tunnels (stops each tunnel process first, then deletes).
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $ids = $validated['ids'];
        $userId = auth()->id();

        $query = Tunnel::whereIn('id', $ids);
        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            });
        }

        $tunnels = $query->get();
        if ($tunnels->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No matching tunnels found to delete.',
            ], 404);
        }

        $count = 0;
        $names = [];

        foreach ($tunnels as $tunnel) {
            try {
                // Ensure tunnel is stopped before deletion
                $this->manager->stopTunnel($tunnel);
                $this->manager->deleteNamedTunnel($tunnel);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error stopping tunnel ID {$tunnel->id} during bulk delete: " . $e->getMessage());
            }

            $names[] = $tunnel->name;
            $tunnel->delete();
            $count++;
        }

        $label = $count === 1 ? "Tunnel '{$names[0]}'" : "{$count} Tunnels (" . implode(', ', array_slice($names, 0, 3)) . ($count > 3 ? '...' : '') . ")";
        Activity::create([
            'user_id' => $userId,
            'description' => "Stopped & Deleted: {$label}",
            'type' => 'deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Successfully stopped and deleted {$count} " . ($count === 1 ? 'tunnel' : 'tunnels') . ".",
            'deleted_count' => $count,
        ]);
    }
}
