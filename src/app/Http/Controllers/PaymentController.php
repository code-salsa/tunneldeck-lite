<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Services\TunnelDeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Get active Razorpay gateway credentials and Pro plan pricing for checkout SDK.
     */
    public function config(TunnelDeckService $tunnelDeck): JsonResponse
    {
        $config = $tunnelDeck->getPaymentConfig();

        return response()->json($config);
    }

    /**
     * Process successful Razorpay payment for Pro Plan Upgrade.
     */
    public function proSuccess(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => 'required|string|max:255',
            'order_id' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|max:10',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $amount = (float) $validated['amount'];
        $currency = strtoupper($validated['currency'] ?? ($user->currency ?: 'INR'));
        $paymentId = $validated['payment_id'];
        $orderId = $validated['order_id'] ?? null;

        // 1. Post to Cloud Licensing Payment Success Callback
        $cloudResp = $tunnelDeck->recordPaymentSuccess([
            'email' => $user->email,
            'name' => $user->name,
            'amount' => $amount,
            'currency' => $currency,
            'payment_type' => 'pro',
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'package' => 'pro',
        ]);

        $cloudData = $cloudResp ? $cloudResp->json() : null;

        // Store and cryptographically verify signed license certificate from licensing authority
        if ($cloudData && !empty($cloudData['signed_license']) && is_array($cloudData['signed_license'])) {
            app(\App\Services\LicenseSignatureVerifier::class)->storeSignedLicense($cloudData['signed_license'], $user);
        } elseif ($cloudData && !empty($cloudData['data']['signed_license']) && is_array($cloudData['data']['signed_license'])) {
            app(\App\Services\LicenseSignatureVerifier::class)->storeSignedLicense($cloudData['data']['signed_license'], $user);
        }

        // 2. Upgrade local user model
        $oldPackage = $user->package;
        $user->package = 'pro';
        $user->currency = $currency;
        $user->license_synced_at = now();
        $user->save();

        // 3. Log Activity
        Activity::create([
            'user_id' => $user->id,
            'description' => "Upgraded from {$oldPackage} to PRO via Razorpay payment ({$paymentId}).",
            'type' => 'success',
        ]);

        // 4. Download and merge Pro version files from licensing API into project src
        $proInstallResult = $tunnelDeck->installProPackageForUser($user);
        $addonInstalled = !empty($proInstallResult['success']);

        return response()->json([
            'success' => true,
            'message' => "Congratulations! You have upgraded to TunnelDeck PRO.",
            'package' => 'pro',
            'addon_installed' => $addonInstalled,
            'files_count' => $proInstallResult['files_count'] ?? 0,
            'files' => $proInstallResult['files'] ?? [],
            'cloud_response' => $cloudData,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'package' => $user->package,
                'max_tunnels' => $user->maxTunnels(),
            ],
        ]);
    }

    /**
     * Process successful Razorpay payment for Buy Me a Coffee donation.
     */
    public function coffeeSuccess(Request $request, TunnelDeckService $tunnelDeck): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => 'required|string|max:255',
            'order_id' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|max:10',
            'message' => 'nullable|string|max:500',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $amount = (float) $validated['amount'];
        $currency = strtoupper($validated['currency'] ?? ($user->currency ?: 'INR'));
        $paymentId = $validated['payment_id'];
        $orderId = $validated['order_id'] ?? null;
        $message = $validated['message'] ?? 'Enjoying TunnelDeck Lite! Here is a coffee ☕';
        $timestamp = now()->toIso8601String();

        // 1. Post to Cloud Licensing Payment Success Callback
        $cloudResp = $tunnelDeck->recordPaymentSuccess([
            'email' => $user->email,
            'name' => $user->name,
            'amount' => $amount,
            'currency' => $currency,
            'payment_type' => 'coffee',
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'message' => $message,
            'timestamp' => $timestamp,
        ]);

        $cloudData = $cloudResp ? $cloudResp->json() : null;

        // 2. Update user coffee donation timestamp (suppresses card for 30 days)
        $user->coffee_donated_at = now();
        $user->save();

        // 3. Log Activity
        Activity::create([
            'user_id' => $user->id,
            'description' => "Supported TunnelDeck with a coffee donation of {$currency} " . number_format($amount, 2) . " ({$paymentId}).",
            'type' => 'info',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Thank you so much for the coffee! ☕ Your support fuels TunnelDeck.",
            'amount' => $amount,
            'currency' => $currency,
            'timestamp' => $timestamp,
            'coffee_donated_at' => $user->coffee_donated_at->toIso8601String(),
            'should_show_coffee' => false,
            'cloud_response' => $cloudData,
        ]);
    }
}

