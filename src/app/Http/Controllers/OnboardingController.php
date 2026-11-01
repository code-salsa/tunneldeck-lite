<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\TunnelDeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    /**
     * Dispatch a 4-digit verification code email to the user before finalizing onboarding.
     */
    public function sendOtp(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $response = $tunnelDeck->sendOtp($validated['email'], $validated['name'] ?? null);

        if (!$response || $response->failed() || !$response->json('success')) {
            $msg = $response?->json('message') ?: 'Could not dispatch verification email. The licensing mail service took longer than expected. Please click Resend Code in a moment.';
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $response->json('message') ?: "A 4-digit verification code has been dispatched to {$validated['email']}.",
            'email' => $validated['email'],
            'expires_in' => $response->json('otp_expires_in', 15),
        ]);
    }

    /**
     * Verify a 4-digit verification code without completing full onboarding.
     */
    public function verifyOtp(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'otp' => 'required|string|size:4',
        ]);

        $response = $tunnelDeck->verifyOtp($validated['email'], $validated['otp']);

        if (!$response || $response->failed() || !$response->json('success')) {
            $msg = $response?->json('message') ?: 'Invalid or expired 4-digit verification code. Please request a new code.';
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully!',
            'verified' => true,
        ]);
    }

    /**
     * Complete full multi-step onboarding and replace default superadmin credentials.
     */
    public function complete(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'password' => 'required|string|min:6|confirmed',
            'otp' => 'nullable|string|size:4',
        ]);

        // If a 4-digit OTP was provided, verify it with the licensing server
        if ($request->filled('otp')) {
            $verifyResp = $tunnelDeck->verifyOtp($validated['email'], $validated['otp']);
            if ($verifyResp && ($verifyResp->failed() || !$verifyResp->json('success'))) {
                $errorMsg = $verifyResp->json('message') ?: 'Invalid or expired 4-digit verification code. Please request a new code.';
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                    'errors' => ['otp' => [$errorMsg]],
                ], 422);
            }
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Replace superadmin credentials and store profile
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = Hash::make($validated['password']);
        $user->city = $validated['city'] ?? null;
        $user->country = $validated['country'] ?? null;
        $user->is_onboarded = true;
        $user->save();

        session()->forget('is_exploring');
        \App\Models\Setting::set('user_' . $user->id . '_is_exploring', 'false');

        // When filled the details and completed the onboarding, clean all data. Make the tunnels list blank.
        \App\Models\Tunnel::query()->delete();

        // Keep current session authenticated with updated user model
        Auth::login($user);

        // 2. Sync to TunnelDeck Licensing Server
        try {
            $tunnelDeck->registerUser([
                'name' => $user->name,
                'email' => $user->email,
                'city' => $user->city,
                'country' => $user->country,
                'package' => 'lite',
                'onboarding_status' => 'completed',
                'is_onboarded' => true,
            ]);

            // Ensure onboarding completion endpoint is called so licensing issues Lite license
            $tunnelDeck->completeOnboarding($user->email, [
                'city' => $user->city,
                'country' => $user->country,
                'package' => 'lite',
            ]);

            $tunnelDeck->logAccess($user->email, [
                'action' => 'first_login',
                'ip_address' => $request->ip(),
                'device' => $request->userAgent() ?? 'Desktop Browser',
                'status' => 'success',
            ]);

            $tunnelDeck->logActivity($user->email, 'onboarding_completed', "User {$user->name} completed full onboarding", [
                'city' => $user->city,
                'country' => $user->country,
                'package' => 'lite',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Licensing sync during onboarding: ' . $e->getMessage());
        }

        // 3. Record local activity log
        Activity::create([
            'user_id' => $user->id,
            'description' => "Superadmin profile initialized for {$user->name} ({$user->email})",
            'type' => 'success',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding completed successfully! Your credentials have been saved.',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'initial' => strtoupper(substr($user->name, 0, 1)),
                'package' => $user->package ?? 'lite',
            ],
        ]);
    }

    /**
     * Explore platform option (requires email at least).
     */
    public function explore(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $nameToSave = !empty($validated['name']) && trim($validated['name']) !== ''
            ? trim($validated['name'])
            : ($user->name && $user->name !== 'TunnelDeck Admin' ? $user->name : 'Platform Explorer');

        $user->name = $nameToSave;
        $user->email = trim($validated['email']);
        if (!empty($validated['city'])) {
            $user->city = $validated['city'];
        }
        if (!empty($validated['country'])) {
            $user->country = $validated['country'];
        }
        $user->is_onboarded = false;
        $user->save();

        session(['is_exploring' => true]);
        \App\Models\Setting::set('user_' . $user->id . '_is_exploring', 'true');

        // Populate sample dummy tunnels for platform exploration
        self::seedDummyTunnels($user->id);

        Auth::login($user);

        // Sync to Licensing Server: save email and name as exploring trial user
        $regResponse = $tunnelDeck->registerUser([
            'name' => $nameToSave,
            'email' => $user->email,
            'city' => $user->city,
            'country' => $user->country,
            'package' => $user->package ?? 'lite',
            'is_exploring' => true,
            'onboarding_status' => 'exploring',
        ]);

        if ($regResponse && $regResponse->successful()) {
            \Illuminate\Support\Facades\Log::info("User {$user->email} successfully registered on licensing portal as exploring trial user.");
            $regData = $regResponse->json('data') ?? [];
            if (!empty($regData['signed_license']) && is_array($regData['signed_license'])) {
                app(\App\Services\LicenseSignatureVerifier::class)->storeVerifiedLicense(
                    $user->email,
                    $regData['signed_license']
                );
            }
        } else {
            \Illuminate\Support\Facades\Log::warning("Licensing portal registration returned non-200 for {$user->email}: " . ($regResponse ? $regResponse->status() : 'null response'));
        }

        $tunnelDeck->logAccess($user->email, [
            'action' => 'explore_platform',
            'ip_address' => $request->ip(),
            'device' => $request->userAgent() ?? 'Desktop Browser',
            'status' => 'success',
        ]);

        $tunnelDeck->logActivity($user->email, 'explore_started', "User {$user->email} entered platform exploration", [
            'package' => 'lite',
            'onboarding_status' => 'exploring',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'description' => "User {$user->email} started exploring platform",
            'type' => 'info',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Welcome to TunnelDeck! Exploration mode active.',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'initial' => strtoupper(substr($user->name, 0, 1)),
                'package' => $user->package ?? 'lite',
            ],
        ]);
    }

    /**
     * Populate sample dummy tunnels for platform exploration.
     */
    public static function seedDummyTunnels(?int $userId = null): void
    {
        // Remove previous tunnels before populating dummy exploration data
        \App\Models\Tunnel::query()->delete();

        $dummyTunnels = [
            [
                'name' => 'Demo Next.js Frontend',
                'port' => 3000,
                'status' => 'stopped',
                'public_url' => 'https://demo-nextjs-app.trycloudflare.com',
                'custom_subdomain' => 'demo-nextjs',
                'is_dummy' => true,
            ],
            [
                'name' => 'Sample REST API Backend',
                'port' => 8000,
                'status' => 'stopped',
                'public_url' => 'https://sample-rest-api.trycloudflare.com',
                'custom_subdomain' => 'sample-api',
                'is_dummy' => true,
            ],
            [
                'name' => 'Mock Payment Webhook',
                'port' => 4242,
                'status' => 'stopped',
                'public_url' => 'https://mock-payment-hook.trycloudflare.com',
                'custom_subdomain' => 'mock-hook',
                'is_dummy' => true,
            ],
        ];

        foreach ($dummyTunnels as $tunnelData) {
            \App\Models\Tunnel::create(
                array_merge($tunnelData, ['user_id' => $userId])
            );
        }
    }
}

