<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class HardwareFingerprintService
{
    protected ?string $cachedHwid = null;

    /**
     * Get the persistent, unique Hardware ID (HWID) for this device.
     * Format: TD-HWID-XXXX-XXXX-XXXX-XXXX
     */
    public function getHwid(): string
    {
        if ($this->cachedHwid !== null) {
            return $this->cachedHwid;
        }

        // 1. Check persistent settings storage first
        $stored = Setting::get('device_hwid');
        if (!empty($stored) && is_string($stored) && preg_match('/^TD-HWID-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $stored)) {
            $this->cachedHwid = $stored;
            return $this->cachedHwid;
        }

        // 2. Compute hardware-bound raw fingerprint
        $raw = $this->collectRawIdentifiers();
        $hash = hash('sha256', 'TUNNELDECK_HWID_SALT_v1:' . $raw);

        // 3. Format as TD-HWID-XXXX-XXXX-XXXX-XXXX (16 uppercase chars in 4 blocks)
        $clean = strtoupper(substr($hash, 0, 16));
        $hwid = sprintf(
            'TD-HWID-%s-%s-%s-%s',
            substr($clean, 0, 4),
            substr($clean, 4, 4),
            substr($clean, 8, 4),
            substr($clean, 12, 4)
        );

        $this->cachedHwid = $hwid;

        // Persist to settings
        try {
            Setting::set('device_hwid', $hwid);
        } catch (\Throwable $e) {
            // DB might be migrating or locked
        }

        return $this->cachedHwid;
    }

    /**
     * Manually override or set HWID (primarily useful for testing).
     */
    public function setHwidForTesting(?string $hwid): void
    {
        $this->cachedHwid = $hwid;
        if ($hwid !== null) {
            Setting::set('device_hwid', $hwid);
        } else {
            Setting::forget('device_hwid');
        }
    }

    /**
     * Collect raw hardware identifiers across Windows, Linux, and macOS.
     */
    protected function collectRawIdentifiers(): string
    {
        $identifiers = [
            'os' => PHP_OS_FAMILY,
            'hostname' => gethostname() ?: 'localhost',
            'arch' => php_uname('m'),
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $identifiers['machine_guid'] = $this->getWindowsMachineGuid();
            $identifiers['motherboard_uuid'] = $this->getWindowsMotherboardUuid();
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $identifiers['mac_uuid'] = $this->getMacPlatformUuid();
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $identifiers['linux_machine_id'] = $this->getLinuxMachineId();
            $identifiers['linux_product_uuid'] = $this->getLinuxProductUuid();
        }

        // Ensure we always have non-empty data even in constrained environments
        $filtered = array_filter($identifiers);
        if (count($filtered) <= 2) {
            $identifiers['fallback_seed'] = $this->getFallbackSeed();
        }

        return json_encode($identifiers);
    }

    /**
     * Windows: Query MachineGuid from registry.
     */
    protected function getWindowsMachineGuid(): ?string
    {
        try {
            $output = @shell_exec('reg query "HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Cryptography" /v MachineGuid 2>nul');
            if ($output && preg_match('/MachineGuid\s+REG_SZ\s+([a-zA-Z0-9-]+)/i', $output, $matches)) {
                return trim($matches[1]);
            }
        } catch (\Throwable $e) {
            Log::debug('HardwareFingerprint: Windows MachineGuid lookup failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Windows: Query Motherboard UUID.
     */
    protected function getWindowsMotherboardUuid(): ?string
    {
        try {
            // Try PowerShell CIM instance first
            $output = @shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_ComputerSystemProduct).UUID" 2>nul');
            $clean = trim((string) $output);
            if (!empty($clean) && preg_match('/^[a-fA-F0-9-]+$/', $clean) && strtolower($clean) !== 'none') {
                return $clean;
            }

            // Fallback to wmic
            $outputWmic = @shell_exec('wmic csproduct get uuid 2>nul');
            if ($outputWmic && preg_match('/[a-fA-F0-9-]{32,36}/i', $outputWmic, $matches)) {
                return trim($matches[0]);
            }
        } catch (\Throwable $e) {
            Log::debug('HardwareFingerprint: Windows Motherboard UUID lookup failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * macOS: Query IOPlatformUUID.
     */
    protected function getMacPlatformUuid(): ?string
    {
        try {
            $output = @shell_exec('ioreg -rd1 -c IOPlatformExpertDevice 2>/dev/null');
            if ($output && preg_match('/"IOPlatformUUID"\s*=\s*"([^"]+)"/i', $output, $matches)) {
                return trim($matches[1]);
            }
        } catch (\Throwable $e) {
            Log::debug('HardwareFingerprint: Mac IOPlatformUUID lookup failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Linux: Read /etc/machine-id or /var/lib/dbus/machine-id.
     */
    protected function getLinuxMachineId(): ?string
    {
        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $path) {
            if (file_exists($path) && is_readable($path)) {
                $content = trim((string) @file_get_contents($path));
                if (!empty($content)) {
                    return $content;
                }
            }
        }

        return null;
    }

    /**
     * Linux: Read /sys/class/dmi/id/product_uuid.
     */
    protected function getLinuxProductUuid(): ?string
    {
        $path = '/sys/class/dmi/id/product_uuid';
        if (file_exists($path) && is_readable($path)) {
            $content = trim((string) @file_get_contents($path));
            if (!empty($content)) {
                return $content;
            }
        }

        return null;
    }

    /**
     * Fallback seed persisted to file if low-level hardware inspection fails.
     */
    protected function getFallbackSeed(): string
    {
        $appDataDir = base_path('.app_data');
        if (!is_dir($appDataDir)) {
            @mkdir($appDataDir, 0755, true);
        }

        $seedFile = $appDataDir . DIRECTORY_SEPARATOR . 'device_fingerprint.seed';
        if (file_exists($seedFile) && is_readable($seedFile)) {
            $seed = trim((string) @file_get_contents($seedFile));
            if (!empty($seed)) {
                return $seed;
            }
        }

        $newSeed = bin2hex(random_bytes(32));
        @file_put_contents($seedFile, $newSeed);
        return $newSeed;
    }

    /**
     * Get detailed device inspection metadata for debugging / UI display.
     */
    public function getDeviceDetails(): array
    {
        return [
            'hwid' => $this->getHwid(),
            'platform' => PHP_OS_FAMILY,
            'hostname' => gethostname() ?: 'localhost',
            'os_version' => php_uname('r'),
            'architecture' => php_uname('m'),
        ];
    }
}

