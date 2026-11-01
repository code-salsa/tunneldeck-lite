<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class CodeIntegrityService
{
    /**
     * Testing bypass flag to permit unit tests without generating manifests every time.
     */
    public static bool $enforceInTesting = false;

    /**
     * List of critical security & licensing files protected by Layer 4 Integrity Guard.
     */
    protected array $protectedFiles = [
        'app/Models/User.php',
        'app/Services/LicenseSignatureVerifier.php',
        'app/Services/HardwareFingerprintService.php',
        'app/Services/TunnelDeckService.php',
        'app/Services/CodeIntegrityService.php',
    ];

    /**
     * Get the list of protected files relative to base_path().
     */
    public function getProtectedFiles(): array
    {
        return $this->protectedFiles;
    }

    /**
     * Get the path to the integrity manifest file.
     */
    public function getManifestPath(): string
    {
        return storage_path('app/integrity.manifest');
    }

    /**
     * Compute a normalized SHA-256 hash of a file's contents.
     * Strips OS line-ending differences (\r\n vs \n) and trailing whitespace
     * to guarantee cross-platform deterministic hashing.
     */
    public function computeFileHash(string $relativePath): ?string
    {
        $fullPath = base_path($relativePath);
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            return null;
        }

        $raw = file_get_contents($fullPath);
        if ($raw === false) {
            return null;
        }

        // Normalize line endings to LF and trim whitespace
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $normalized = trim($normalized);

        return hash('sha256', $normalized);
    }

    /**
     * Generate a cryptographically signed integrity manifest for all protected files.
     */
    public function generateManifest(): array
    {
        $hashes = [];
        foreach ($this->protectedFiles as $file) {
            $hash = $this->computeFileHash($file);
            if ($hash !== null) {
                $hashes[$file] = $hash;
            }
        }

        $secret = config('app.key', 'TUNNELDECK_INTEGRITY_SALT_2026');
        $signature = hash_hmac('sha256', json_encode($hashes), $secret);

        $manifest = [
            'version' => '1.0.0',
            'generated_at' => now()->toIso8601String(),
            'algorithm' => 'HMAC-SHA256',
            'files' => $hashes,
            'signature' => $signature,
        ];

        // 1. Persist to storage file
        $manifestDir = dirname($this->getManifestPath());
        if (!is_dir($manifestDir)) {
            @mkdir($manifestDir, 0755, true);
        }
        file_put_contents($this->getManifestPath(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Persist to settings table for redundancy
        try {
            Setting::set('code_integrity_manifest', json_encode($manifest));
        } catch (\Throwable $e) {
            // DB might be migrating or locked
        }

        return $manifest;
    }

    /**
     * Load the current integrity manifest from file or settings.
     */
    public function loadManifest(): ?array
    {
        // 1. Try file first
        $path = $this->getManifestPath();
        if (file_exists($path) && is_readable($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data) && isset($data['files'], $data['signature'])) {
                return $data;
            }
        }

        // 2. Fallback to settings
        $stored = Setting::get('code_integrity_manifest');
        if (!empty($stored) && is_string($stored)) {
            $data = json_decode($stored, true);
            if (is_array($data) && isset($data['files'], $data['signature'])) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Verify the integrity of all protected files against the manifest.
     */
    public function verifyIntegrity(): array
    {
        // Testing bypass for environments where code is being modified in memory
        if (app()->environment('testing') && !static::$enforceInTesting) {
            return [
                'valid' => true,
                'tampered_files' => [],
                'verified_files' => $this->protectedFiles,
                'message' => 'Integrity verification bypassed in testing environment.',
            ];
        }

        $manifest = $this->loadManifest();
        if (!$manifest) {
            // If no manifest has been generated yet, allow clean first-run unless in strict mode
            return [
                'valid' => true,
                'tampered_files' => [],
                'verified_files' => [],
                'message' => 'No integrity manifest installed. Running in unsealed development mode.',
            ];
        }

        // 1. Verify HMAC signature of manifest itself
        $secret = config('app.key', 'TUNNELDECK_INTEGRITY_SALT_2026');
        $expectedSignature = hash_hmac('sha256', json_encode($manifest['files']), $secret);
        if (!hash_equals($expectedSignature, (string) ($manifest['signature'] ?? ''))) {
            Log::emergency('CodeIntegrityService: Integrity manifest signature mismatch! The manifest has been tampered with.');
            return [
                'valid' => false,
                'tampered_files' => array_keys($manifest['files']),
                'verified_files' => [],
                'message' => 'Integrity manifest signature is invalid. Tampering detected.',
            ];
        }

        // 2. Verify each file's current hash against expected hash
        $tampered = [];
        $verified = [];

        foreach ($manifest['files'] as $file => $expectedHash) {
            $currentHash = $this->computeFileHash($file);
            if ($currentHash === null || !hash_equals(strtolower($expectedHash), strtolower($currentHash))) {
                $tampered[] = $file;
                Log::warning("CodeIntegrityService: Tampered file detected: {$file}. Expected {$expectedHash}, got " . ($currentHash ?? 'MISSING'));
            } else {
                $verified[] = $file;
            }
        }

        $isValid = count($tampered) === 0;

        return [
            'valid' => $isValid,
            'tampered_files' => $tampered,
            'verified_files' => $verified,
            'message' => $isValid
                ? 'All protected files verified successfully.'
                : 'Integrity violation! One or more protected files have been modified or patched.',
        ];
    }

    /**
     * Quick boolean check used by licensing gatekeepers.
     */
    public function isIntegrityValid(): bool
    {
        return $this->verifyIntegrity()['valid'];
    }
}

