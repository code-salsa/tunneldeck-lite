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

        $result = $tunnelDeck->syncUserLicense($user);

        return response()->json($result);
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
