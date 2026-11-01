<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Routes or URL patterns exempt from suspension check.
     */
    protected array $exemptRoutes = [
        'suspended',
        'account.suspended',
        'logout',
        'login',
        'api/license/sync',
        'license.sync',
        'api/connectivity-check',
        'api/license/device-info',
        'license.device.info',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isSuspended') && $user->isSuspended()) {
            // Check if current route or path is exempt
            foreach ($this->exemptRoutes as $exempt) {
                if ($request->routeIs($exempt) || $request->is($exempt) || $request->is('api/license/*')) {
                    return $next($request);
                }
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'Your account access has been suspended by the administrator.',
                    'suspended' => true,
                ], 403);
            }

            return redirect()->route('account.suspended');
        }

        return $next($request);
    }
}

