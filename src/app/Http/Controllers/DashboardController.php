<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CustomDomain;
use App\Models\Tunnel;
use App\Services\TunnelHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected TunnelHealthService $healthService;

    public function __construct(TunnelHealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    /**
     * Display the main application dashboard with initial state.
     */
    public function index(Request $request): View
    {
        $userId = Auth::id();

        $tunnels = Tunnel::when($userId, fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($tunnel) {
                $tunnel->health = $this->healthService->checkHealth($tunnel);
                return $tunnel;
            });

        $totalTunnels = $tunnels->count();
        $runningTunnels = $tunnels->where('status', 'running')->count();
        $stoppedTunnels = $tunnels->where('status', 'stopped')->count();

        $metrics = [
            'total' => $totalTunnels,
            'active' => $runningTunnels,
            'paused' => $stoppedTunnels,
            'total_requests' => $tunnels->sum('connections_count'),
        ];

        $activities = Activity::when($userId, fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $domains = class_exists(CustomDomain::class)
            ? CustomDomain::when($userId, fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'))
                ->withCount('tunnels')
                ->orderBy('created_at', 'desc')
                ->get()
            : collect();

        return view('dashboard', [
            'user' => Auth::user(),
            'tunnels' => $tunnels,
            'metrics' => $metrics,
            'activities' => $activities,
            'domains' => $domains,
        ]);
    }
}

