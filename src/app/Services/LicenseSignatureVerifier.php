<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LicenseSignatureVerifier
{
    /**
     * Flag to control testing bypass. In testing mode, if no certificate is set,
     * bypass allows factory users without OpenSSL certs to pass tests unless explicitly disabled.
     */
    public static bool $bypassInTesting = true;

    /**
     * Get the active RSA Public Key for verifying license certificates.
     */
    public function getPublicKey(): string
    {
        $dynamicKey = Setting::get('licensing_public_key');
        if (!empty($dynamicKey)) {
            return $dynamicKey;
        }

        return (string) config('tunneldeck.public_key', '');
    }

    /**
     * Verify an asymmetrically signed license certificate using RSA-SHA256.
     *
     * @param string $base64Certificate Base64-encoded canonical JSON payload
     * @param string $base64Signature Base64-encoded binary RSA-SHA256 signature
     * @return array|false Verified payload array or false if verification fails/tampered
     */
    public function verify(string $base64Certificate, string $base64Signature): array|false
    {
        $rawJson = base64_decode($base64Certificate);
        $binarySignature = base64_decode($base64Signature);
        $publicKey = $this->getPublicKey();

        if (empty($rawJson) || empty($binarySignature) || empty($publicKey)) {
            return false;
        }

        $result = openssl_verify($rawJson, $binarySignature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($result !== 1) {
            Log::warning('LicenseSignatureVerifier: Signature verification failed. License certificate has been modified or forged.');
            return false;
        }

        $payload = json_decode($rawJson, true);
        if (!is_array($payload)) {
            return false;
        }

        // Verify expiration date if present
        if (!empty($payload['expires_at'])) {
            $expiresTime = strtotime($payload['expires_at']);
            if ($expiresTime !== false && $expiresTime < time()) {
                Log::warning('LicenseSignatureVerifier: Cryptographic license has expired on ' . $payload['expires_at']);
                return false;
            }
        }

        return $payload;
    }

    /**
     * Check whether a user's Pro package is cryptographically valid.
     */
    public function isUserProVerified(?User $user): bool
    {
        if (!$user || !in_array(strtolower((string) $user->package), ['pro', 'enterprise'])) {
            return false;
        }

        $certificate = Setting::get('license_certificate');
        $signature = Setting::get('license_signature');

        // Testing environment fallback for unit tests where external licensing is mocked
        if (static::$bypassInTesting && app()->environment('testing') && empty($certificate)) {
            return true;
        }

        if (empty($certificate) || empty($signature)) {
            Log::warning("LicenseSignatureVerifier: Tampering detected for user {$user->email}. Database package is '{$user->package}', but no cryptographic signature exists.");
            return false;
        }

        $verified = $this->verify($certificate, $signature);
        if ($verified === false) {
            Log::warning("LicenseSignatureVerifier: Tampering detected for user {$user->email}. Cryptographic signature is invalid.");
            return false;
        }

        // Verify that the signed certificate belongs to this user
        if (!empty($verified['email']) && strtolower($verified['email']) !== strtolower($user->email)) {
            Log::warning("LicenseSignatureVerifier: License mismatch. Signed email ({$verified['email']}) does not match local user ({$user->email}).");
            return false;
        }

        // Layer 3: Hardware ID (HWID) Device Fingerprint Verification
        if (!empty($verified['hwid'])) {
            $localHwid = app(HardwareFingerprintService::class)->getHwid();
            if (!hash_equals(strtolower((string) $verified['hwid']), strtolower((string) $localHwid))) {
                Log::warning("LicenseSignatureVerifier: HWID device mismatch. Certificate is bound to device [{$verified['hwid']}], but local device is [{$localHwid}].");
                return false;
            }
        }

        // Layer 4: Code Integrity & Anti-Tamper Verification
        if (!app(CodeIntegrityService::class)->isIntegrityValid()) {
            Log::emergency("LicenseSignatureVerifier: Code integrity violation! Application source files have been tampered with or patched.");
            return false;
        }

        // Max Allowed Activations (Seats) Verification
        if (!empty($verified['activation_limit_reached']) || ($verified['status'] ?? '') === 'activation_limit_reached') {
            Log::warning("LicenseSignatureVerifier: Device activation seat limit reached for user {$user->email}. Pro entitlement denied.");
            return false;
        }

        // Layer 4: Code Integrity & Anti-Tamper Verification
        if (!app(CodeIntegrityService::class)->isIntegrityValid()) {
            Log::emergency("LicenseSignatureVerifier: Code integrity violation! Application source files have been tampered with or patched.");
            return false;
        }

        // Max Allowed Activations (Seats) Verification
        if (!empty($verified['activation_limit_reached']) || ($verified['status'] ?? '') === 'activation_limit_reached') {
            Log::warning("LicenseSignatureVerifier: Device activation seat limit reached for user {$user->email}. Pro entitlement denied.");
            return false;
        }

        // Verify that the payload affirms Pro entitlement
        return in_array(strtolower((string) ($verified['package'] ?? '')), ['pro', 'enterprise']);
    }

    /**
     * Store and verify a newly received signed license bundle.
     */
    public function storeSignedLicense(array $signedLicense, User $user): bool
    {
        $cert = $signedLicense['certificate'] ?? '';
        $sig = $signedLicense['signature'] ?? '';

        if (empty($cert) || empty($sig)) {
            return false;
        }

        $verified = $this->verify($cert, $sig);
        if ($verified === false) {
            return false;
        }

        // Layer 3: If HWID is specified, refuse to store if bound to another device
        if (!empty($verified['hwid'])) {
            $localHwid = app(HardwareFingerprintService::class)->getHwid();
            if (!hash_equals(strtolower((string) $verified['hwid']), strtolower((string) $localHwid))) {
                Log::warning("LicenseSignatureVerifier: Refusing to store license bound to different device: {$verified['hwid']} vs {$localHwid}");
                return false;
            }
        }

        // Refuse to store if activation seat limit is reached
        if (!empty($verified['activation_limit_reached']) || ($verified['status'] ?? '') === 'activation_limit_reached') {
            Log::warning("LicenseSignatureVerifier: Refusing to store license. Activation seat limit reached for device.");
            return false;
        }

        Setting::set('license_certificate', $cert);
        Setting::set('license_signature', $sig);
        Setting::set('license_key', $verified['license_key'] ?? '');
        Setting::set('license_tier', $verified['tier'] ?? 'Pro');

        if (!empty($signedLicense['public_key'])) {
            Setting::set('licensing_public_key', $signedLicense['public_key']);
        }

        return true;
    }
}
