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
        ]);

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

        if (!empty($validated['name'])) {
            $user->name = $validated['name'];
        }
        $user->email = $validated['email'];
        if (!empty($validated['city'])) {
            $user->city = $validated['city'];
        }
        if (!empty($validated['country'])) {
            $user->country = $validated['country'];
        }
        $user->is_onboarded = true;
        $user->save();

        Auth::login($user);

        // Sync to Licensing Server
        $tunnelDeck->registerUser([
            'name' => $user->name ?: 'Platform Explorer',
            'email' => $user->email,
            'city' => $user->city,
            'country' => $user->country,
            'package' => 'lite',
        ]);

        $tunnelDeck->logAccess($user->email, [
            'action' => 'explore_platform',
            'ip_address' => $request->ip(),
            'device' => $request->userAgent() ?? 'Desktop Browser',
            'status' => 'success',
        ]);

        $tunnelDeck->logActivity($user->email, 'explore_started', "User {$user->email} entered platform exploration", [
            'package' => 'lite',
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
}

