<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CustomDomain;
use App\Models\Tunnel;
use App\Services\TunnelDeckService;
use App\Services\TunnelHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected TunnelHealthService $healthService;
    protected TunnelDeckService $tunnelDeck;

    public function __construct(TunnelHealthService $healthService, TunnelDeckService $tunnelDeck)
    {
        $this->healthService = $healthService;
        $this->tunnelDeck = $tunnelDeck;
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

        $user = Auth::user() ?? \App\Models\User::first();
        $isExploring = false;
        if ($user && !$user->is_onboarded) {
            $isExploring = $user->isExploring();

            // Report exploring telemetry if not reported recently
            if ($isExploring && strtolower($user->email) !== 'admin@tunneldeck.online') {
                $lastPing = \App\Models\Setting::get('user_' . $user->id . '_last_telemetry_ping');
                if (!$lastPing || (time() - (int) $lastPing > 3600)) {
                    try {
                        $this->tunnelDeck->reportExploringTelemetry($user);
                        \App\Models\Setting::set('user_' . $user->id . '_last_telemetry_ping', (string) time());
                    } catch (\Throwable $e) {}
                }
            }

            // Ensure dummy tunnels exist when exploring
            if ($isExploring && Tunnel::count() === 0) {
                OnboardingController::seedDummyTunnels($user->id);
                $tunnels = Tunnel::when($userId, fn ($q) => $q->where('user_id', $userId)->orWhereNull('user_id'))
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($tunnel) {
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
                $metrics = [
                    'total' => $totalTunnels,
                    'active' => $runningTunnels,
                    'paused' => $stoppedTunnels,
                    'total_requests' => $tunnels->sum('connections_count'),
                ];
            }
        }

        $limitInfo = $user ? $this->tunnelDeck->getTunnelLimit($user) : [
            'max_tunnels' => 5,
            'is_unlimited' => false,
            'total_created' => $totalTunnels,
            'remaining' => max(0, 5 - $totalTunnels),
            'limit_reached' => $totalTunnels >= 5,
            'package' => 'lite',
            'message' => $totalTunnels >= 5 ? 'You have reached the number of allowed tunnel creation. Upgrade to a pro plan to enjoy unlimited tunnels.' : null,
        ];

        // Retrieve dynamic pricing from licensing API
        $paymentConfig = $this->tunnelDeck->getPaymentConfig();
        $net = app(\App\Services\SystemInfoService::class)->getPublicNetworkDetails();
        $countryCode = strtoupper((string)($net['country_code'] ?? ($net['country'] ?? '')));
        $isIndia = in_array($countryCode, ['IN', 'INDIA', 'BHARAT']) 
            || strtoupper((string)($user?->currency ?? '')) === 'INR' 
            || in_array(strtoupper((string)($user?->country ?? '')), ['IN', 'INDIA', 'BHARAT']);

        $proPlan = $paymentConfig['pro_plan'] ?? [];
        $priceInr = $proPlan['price_inr'] ?? ($proPlan['price'] ?? 6999);
        $priceUsd = $proPlan['price_usd'] ?? 99;

        if ($isIndia) {
            $userCurrency = 'INR';
            $proPriceDisplay = '₹' . number_format((float) $priceInr);
        } else {
            $userCurrency = 'USD';
            $proPriceDisplay = '$' . number_format((float) $priceUsd);
        }

        return view('dashboard', [
            'user' => $user,
            'tunnels' => $tunnels,
            'metrics' => $metrics,
            'activities' => $activities,
            'domains' => $domains,
            'limitInfo' => $limitInfo,
            'isLimitReached' => (bool) ($limitInfo['limit_reached'] ?? false),
            'isExploring' => $isExploring,
            'userCurrency' => $userCurrency,
            'proPriceDisplay' => $proPriceDisplay,
        ]);
    }
}

