<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Services\TunnelDeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LicenseController extends Controller
{
    /**
     * Synchronize license and package tier from licensing.tunneldeck.online to this platform.
     */
    public function sync(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'redirect' => '/login',
            ], 401);
        }

        $result = $tunnelDeck->syncUserLicense($user);

        if (!empty($result['client_deleted']) || !empty($result['reset_to_defaults'])) {
            return response()->json(array_merge($result, [
                'redirect' => '/login',
            ]), 200);
        }

        return response()->json($result);
    }

    /**
     * Download and merge Pro edition codebase into project src.
     */
    public function installProPackage(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $result = $tunnelDeck->installProPackageForUser($user);

        return response()->json($result);
    }

    /**
     * Webhook/Sync endpoint invoked when a client is deleted on licensing platform.
     * Automatically resets the local SQLite database to factory defaults.
     */
    public function clientDeletedWebhook(Request $request): JsonResponse
    {
        $email = $request->input('email');
        \Illuminate\Support\Facades\Log::channel('tunnels')->info("LicenseController: Received client-deleted event for email: " . ($email ?: 'all'));

        $currentUser = User::first();
        if ($email && $currentUser && strtolower($currentUser->email) !== strtolower($email) && $currentUser->is_onboarded) {
            return response()->json([
                'success' => true,
                'ignored' => true,
                'message' => "Client {$email} does not match local user {$currentUser->email}.",
            ]);
        }

        // If local instance is already in factory default mode or unonboarded, skip reset
        if (!$currentUser || !$currentUser->is_onboarded || strtolower($currentUser->email) === 'admin@tunneldeck.online') {
            return response()->json([
                'success' => true,
                'ignored' => true,
                'message' => "Local user is already in default exploration state.",
            ]);
        }

        $result = TunnelDeckService::resetToDefaults();

        return response()->json([
            'success' => true,
            'client_deleted' => true,
            'reset_to_defaults' => true,
            'redirect' => '/login',
            'message' => 'Client deleted from licensing platform. Local database reset to defaults.',
            'details' => $result,
        ]);
    }

    /**
     * Get local hardware device fingerprinting details (Layer 3).
     */
    public function deviceInfo(\App\Services\HardwareFingerprintService $hwidService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'device' => $hwidService->getDeviceDetails(),
            'is_pro_verified' => Auth::user()?->isPro() ?? false,
        ]);
    }

    /**
     * Get activation seats information for the current user and device.
     */
    public function seatsInfo(TunnelDeckService $tunnelDeck): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $response = $tunnelDeck->getUserActivations($user->email);
        $localHwid = app(\App\Services\HardwareFingerprintService::class)->getHwid();

        if ($response && $response->successful()) {
            $data = $response->json();
            $data['local_hwid'] = $localHwid;
            $data['is_current_device_bound'] = in_array($localHwid, $data['bound_hwids'] ?? [], true);
            return response()->json($data);
        }

        return response()->json([
            'success' => true,
            'max_activations' => 1,
            'activations_count' => 1,
            'available_seats' => 0,
            'local_hwid' => $localHwid,
            'bound_hwids' => [$localHwid],
            'is_current_device_bound' => true,
        ]);
    }

    /**
     * Test plan upgrade from this platform to licensing server and sync back.
     */
    public function upgradeTest(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        $validated = $request->validate([
            'to_package' => 'required|string|in:lite,pro,enterprise',
            'amount' => 'nullable|numeric',
            'currency' => 'nullable|string|max:10',
        ]);

        /** @var User $user */
        $user = Auth::user();

        if ($user && $user->isExploring()) {
            return response()->json([
                'success' => false,
                'message' => 'Platform settings cannot be changed in simulation mode. Please complete onboarding first.',
            ], 403);
        }

        $toPackage = $validated['to_package'];
        $fromPackage = $user->package ?? 'lite';
        $amount = (float) ($validated['amount'] ?? ($toPackage === 'pro' ? 499.00 : ($toPackage === 'enterprise' ? 1499.00 : 0.00)));
        $currency = $validated['currency'] ?? ($user->currency ?? 'INR');

        // 1. Send upgrade record to Licensing Server
        $upgradeResp = $tunnelDeck->logUpgrade($user->email, $toPackage, $amount, [
            'from_package' => $fromPackage,
            'currency' => $currency,
            'payment_reference' => 'TEST_TXN_' . strtoupper(bin2hex(random_bytes(4))),
        ]);

        // 2. Sync upgraded license back to local platform
        $syncResult = $tunnelDeck->syncUserLicense($user);

        if ($toPackage === 'pro') {
            $tunnelDeck->installProPackageForUser($user);
        }

        return response()->json([
            'success' => $syncResult['success'],
            'message' => "Upgrade to " . strtoupper($toPackage) . " submitted and synchronized to local instance!",
            'package' => $user->package,
            'cloud_response' => $upgradeResp ? $upgradeResp->json() : null,
            'sync_result' => $syncResult,
        ]);
    }

    /**
     * Run full end-to-end diagnostic test of both Outgoing (client details) and Incoming (license upgrade).
     */
    public function runTestFlow(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $testEmail = $request->input('email', $user->email);
        $testName = $request->input('name', $user->name);

        $results = [
            'email' => $testEmail,
            'steps' => [],
            'all_passed' => true,
        ];

        // Step 1: Outgoing - Client Registration
        $t1 = microtime(true);
        $regResp = $tunnelDeck->registerUser([
            'name' => $testName,
            'email' => $testEmail,
            'city' => $user->city ?? 'Bengaluru',
            'country' => $user->country ?? 'India',
            'package' => 'lite',
        ]);
        $duration1 = round((microtime(true) - $t1) * 1000);
        $s1Success = ($regResp && $regResp->successful()) || ($regResp && $regResp->status() === 422 && str_contains($regResp->body(), 'already been taken'));
        $results['steps'][] = [
            'name' => '1. Outgoing: Client Details Submission (User Registration)',
            'endpoint' => 'POST /api/v1/users/register',
            'status' => $regResp ? $regResp->status() : 500,
            'success' => $s1Success,
            'duration_ms' => $duration1,
            'note' => ($regResp && $regResp->status() === 422) ? 'Account already registered on licensing server' : 'New registration created',
            'response' => $regResp ? $regResp->json() : null,
        ];
        if (!$s1Success) $results['all_passed'] = false;

        // Step 2: Outgoing - Record Access Log
        $t2 = microtime(true);
        $accessResp = $tunnelDeck->logAccess($testEmail, [
            'action' => 'diagnostic_test',
            'ip_address' => $request->ip(),
            'device' => 'Diagnostic Tester',
            'status' => 'success',
        ]);
        $duration2 = round((microtime(true) - $t2) * 1000);
        $s2Success = $accessResp && $accessResp->successful();
        $results['steps'][] = [
            'name' => '2. Outgoing: Access Log Recording',
            'endpoint' => "POST /api/v1/users/{email}/access-logs",
            'status' => $accessResp ? $accessResp->status() : 500,
            'success' => $s2Success,
            'duration_ms' => $duration2,
            'response' => $accessResp ? $accessResp->json() : null,
        ];
        if (!$s2Success) $results['all_passed'] = false;

        // Step 3: Outgoing - Record Activity Log
        $t3 = microtime(true);
        $actResp = $tunnelDeck->logActivity($testEmail, 'test_flow_executed', 'End-to-end license diagnostic test executed', [
            'platform' => 'TunnelDeck Lite',
            'client_ip' => $request->ip(),
        ]);
        $duration3 = round((microtime(true) - $t3) * 1000);
        $s3Success = $actResp && $actResp->successful();
        $results['steps'][] = [
            'name' => '3. Outgoing: Activity Log Recording',
            'endpoint' => "POST /api/v1/users/{email}/activity-logs",
            'status' => $actResp ? $actResp->status() : 500,
            'success' => $s3Success,
            'duration_ms' => $duration3,
            'response' => $actResp ? $actResp->json() : null,
        ];
        if (!$s3Success) $results['all_passed'] = false;

        // Step 4: Plan Upgrade on Cloud Server (Lite -> Pro)
        $t4 = microtime(true);
        $targetPackage = ($user->package === 'pro') ? 'enterprise' : 'pro';
        $upgResp = $tunnelDeck->logUpgrade($testEmail, $targetPackage, 499.00, [
            'currency' => 'INR',
            'payment_reference' => 'TEST_DIAG_' . time(),
        ]);
        $duration4 = round((microtime(true) - $t4) * 1000);
        $s4Success = $upgResp && $upgResp->successful();
        $results['steps'][] = [
            'name' => "4. Cloud: Plan Upgrade Record ({$targetPackage})",
            'endpoint' => "POST /api/v1/users/{email}/upgrades",
            'status' => $upgResp ? $upgResp->status() : 500,
            'success' => $s4Success,
            'duration_ms' => $duration4,
            'response' => $upgResp ? $upgResp->json() : null,
        ];
        if (!$s4Success) $results['all_passed'] = false;

        // Step 5: Incoming - License Synchronization to Local Platform
        $t5 = microtime(true);
        $syncResult = $tunnelDeck->syncUserLicense($user);
        $duration5 = round((microtime(true) - $t5) * 1000);
        $s5Success = $syncResult['success'];
        $results['steps'][] = [
            'name' => "5. Incoming: License Update & Plan Upgrade Sync to Local Platform",
            'endpoint' => "GET /api/v1/users/{email}",
            'status' => $s5Success ? 200 : 500,
            'success' => $s5Success,
            'duration_ms' => $duration5,
            'local_user_package' => $user->fresh()->package,
            'response' => $syncResult,
        ];
        if (!$s5Success) $results['all_passed'] = false;

        return response()->json($results);
    }

    /**
     * Webhook endpoint to receive remote license updates from licensing.tunneldeck.online
     */
    public function webhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'package' => 'nullable|string',
            'currency' => 'nullable|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found on this local instance.',
            ], 404);
        }

        if (!empty($validated['package'])) {
            $oldPackage = $user->package;
            $user->package = $validated['package'];
            if (!empty($validated['currency'])) {
                $user->currency = $validated['currency'];
            }
            $user->license_synced_at = now();
            $user->save();

            Activity::create([
                'user_id' => $user->id,
                'description' => "Webhook received: Plan updated from {$oldPackage} to {$user->package}",
                'type' => 'success',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully.',
            'package' => $user->package,
        ]);
    }
}
