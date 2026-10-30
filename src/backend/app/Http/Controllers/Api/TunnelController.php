<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tunnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TunnelController extends Controller
{
    /**
     * List all tunnels with statistics.
     */
    public function index(): JsonResponse
    {
        $tunnels = Tunnel::orderBy('created_at', 'desc')->get();
        $totalTunnels = $tunnels->count();
        $activeTunnels = $tunnels->where('status', 'active')->count();
        $totalRequests = $tunnels->sum('connections_count');

        return response()->json([
            'status' => 'success',
            'data' => [
                'tunnels' => $tunnels,
                'metrics' => [
                    'total' => $totalTunnels,
                    'active' => $activeTunnels,
                    'paused' => $tunnels->where('status', 'paused')->count(),
                    'total_requests' => $totalRequests,
                ],
            ],
        ]);
    }

    /**
     * Create a new tunnel.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'subdomain' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:tunnels,subdomain'],
            'protocol' => ['required', 'in:http,https,tcp'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'target_host' => ['nullable', 'string', 'max:100'],
        ]);

        $subdomain = !empty($validated['subdomain'])
            ? Str::slug($validated['subdomain'])
            : Str::slug($validated['name']) . '-' . Str::random(4);

        $targetHost = $validated['target_host'] ?? '127.0.0.1';
        $publicUrl = "https://{$subdomain}.tunneldeck.online";

        $tunnel = Tunnel::create([
            'name' => $validated['name'],
            'subdomain' => $subdomain,
            'protocol' => $validated['protocol'],
            'port' => $validated['port'],
            'target_host' => $targetHost,
            'public_url' => $publicUrl,
            'status' => 'active',
            'connections_count' => 0,
            'last_active_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tunnel created successfully',
            'tunnel' => $tunnel,
        ], 201);
    }

    /**
     * Toggle status of a tunnel.
     */
    public function toggleStatus(Tunnel $tunnel): JsonResponse
    {
        $tunnel->status = $tunnel->status === 'active' ? 'paused' : 'active';
        $tunnel->save();

        return response()->json([
            'status' => 'success',
            'message' => "Tunnel is now {$tunnel->status}",
            'tunnel' => $tunnel,
        ]);
    }

    /**
     * Delete a tunnel.
     */
    public function destroy(Tunnel $tunnel): JsonResponse
    {
        $tunnel->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tunnel deleted successfully',
        ]);
    }
}
