<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TunnelStoreRequest;
use App\Http\Requests\TunnelUpdateRequest;
use App\Models\Activity;
use App\Models\Tunnel;
use App\Services\TunnelHealthService;
use App\Services\TunnelManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TunnelController extends Controller
{
    protected TunnelManagerService $manager;
    protected TunnelHealthService $healthService;

    public function __construct(TunnelManagerService $manager, TunnelHealthService $healthService)
    {
        $this->manager = $manager;
        $this->healthService = $healthService;
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
            return $tunnel;
        });

        $totalTunnels = $tunnels->count();
        $runningTunnels = $tunnels->where('status', 'running')->count();
        $stoppedTunnels = $tunnels->where('status', 'stopped')->count();

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tunnels,name'],
            'port' => ['required', 'integer', 'min:1', 'max:65535', 'unique:tunnels,port'],
            'custom_subdomain' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9-]+$/'],
        ]);

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
        if ($tunnel->status === 'running' && $tunnel->isRunning()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tunnel is already running.',
                'tunnel' => $tunnel,
            ]);
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tunnels,name,' . $tunnel->id],
            'port' => ['required', 'integer', 'min:1', 'max:65535', 'unique:tunnels,port,' . $tunnel->id],
        ]);

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
}
