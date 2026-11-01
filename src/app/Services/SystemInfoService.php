<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SystemInfoService
{
    protected HardwareFingerprintService $hwidService;

    public function __construct(HardwareFingerprintService $hwidService)
    {
        $this->hwidService = $hwidService;
    }

    /**
     * Detect friendly Operating System name (Windows, Linux, macOS).
     */
    public function getOperatingSystem(): string
    {
        $family = PHP_OS_FAMILY;

        if ($family === 'Windows') {
            $versionString = php_uname('v');
            if (preg_match('/build\s*(\d+)/i', $versionString, $m)) {
                $build = (int) $m[1];
                if ($build >= 22000) {
                    return 'Windows 11';
                } elseif ($build >= 10240) {
                    return 'Windows 10';
                }
            }
            return 'Windows';
        }

        if ($family === 'Darwin') {
            $macVer = $this->getMacProductVersion();
            return $macVer ? "macOS {$macVer}" : 'macOS';
        }

        if ($family === 'Linux') {
            $distro = $this->getLinuxDistroName();
            return $distro ?: 'Linux';
        }

        return $family;
    }

    /**
     * Get simple OS family identifier: 'Windows', 'Linux', or 'macOS'.
     */
    public function getOperatingSystemFamily(): string
    {
        $family = PHP_OS_FAMILY;
        if ($family === 'Darwin') {
            return 'macOS';
        }
        return in_array($family, ['Windows', 'Linux']) ? $family : 'Other';
    }

    /**
     * FontAwesome / Lucide icon class for the OS.
     */
    public function getOperatingSystemIcon(): string
    {
        $os = $this->getOperatingSystemFamily();
        return match ($os) {
            'Windows' => 'fa-brands fa-windows',
            'macOS' => 'fa-brands fa-apple',
            'Linux' => 'fa-brands fa-linux',
            default => 'fa-solid fa-desktop',
        };
    }

    /**
     * Resolve public WAN IP address and Geo-location with short timeout & persistent cache.
     */
    public function getPublicNetworkDetails(): array
    {
        $cached = Setting::get('public_network_details');
        if (!empty($cached) && is_array($cached) && !empty($cached['ip']) && !empty($cached['cached_at'])) {
            // Cache valid for 3 hours
            if (time() - $cached['cached_at'] < 10800) {
                return $cached;
            }
        }

        $details = [
            'ip' => null,
            'country' => null,
            'country_code' => null,
            'city' => null,
            'region' => null,
            'timezone' => date_default_timezone_get(),
            'cached_at' => time(),
        ];

        // 1. Try ipwho.is (fast, free, returns JSON with country, city, flag)
        $caPath = TunnelDeckService::getCaBundlePath();
        try {
            $req = Http::timeout(2)->acceptJson();
            if ($caPath) {
                $req = $req->withOptions(['verify' => $caPath]);
            }
            $resp = $req->get('https://ipwho.is/');
            if ($resp->successful() && $resp->json('success') !== false) {
                $json = $resp->json();
                $details['ip'] = $json['ip'] ?? null;
                $details['country'] = $json['country'] ?? null;
                $details['country_code'] = $json['country_code'] ?? null;
                $details['city'] = $json['city'] ?? null;
                $details['region'] = $json['region'] ?? null;
                $details['timezone'] = $json['timezone']['id'] ?? date_default_timezone_get();
            }
        } catch (\Throwable $e) {
            // Fallback to secondary IP lookup
        }

        // 2. Fallback to api.ipify.org if IP is still empty
        if (empty($details['ip'])) {
            try {
                $req2 = Http::timeout(2);
                if ($caPath) {
                    $req2 = $req2->withOptions(['verify' => $caPath]);
                }
                $resp2 = $req2->get('https://api.ipify.org?format=json');
                if ($resp2->successful()) {
                    $details['ip'] = $resp2->json('ip');
                }
            } catch (\Throwable $e) {
                // Network unreachable or offline
            }
        }

        if (!empty($details['ip'])) {
            try {
                Setting::set('public_network_details', $details);
            } catch (\Throwable $e) {}
        }

        return $details;
    }

    /**
     * Get machine hostname.
     */
    public function getHostname(): string
    {
        $name = gethostname();
        return (!empty($name) && $name !== 'localhost') ? $name : php_uname('n');
    }

    /**
     * Compile complete system telemetry payload.
     */
    public function getSystemInfo(?Request $request = null, ?User $user = null): array
    {
        $net = $this->getPublicNetworkDetails();

        $ip = $net['ip'] ?: ($request ? $request->ip() : '127.0.0.1');
        if (in_array($ip, ['127.0.0.1', '::1', null])) {
            $ip = $net['ip'] ?: '127.0.0.1';
        }

        $city = $net['city'] ?: ($user?->city ?: ($request?->input('city') ?: null));
        $country = $net['country'] ?: ($user?->country ?: ($request?->input('country') ?: null));

        // Screen resolution & browser details from frontend request if provided
        $screen = $request ? $request->input('screen_resolution') : null;
        $browserTimezone = $request ? $request->input('browser_timezone') : null;

        $os = $this->getOperatingSystem();
        $osFamily = $this->getOperatingSystemFamily();
        $hwid = $this->hwidService->getHwid();
        $hostname = $this->getHostname();

        return [
            'os' => $os,
            'os_family' => $osFamily,
            'os_icon' => $this->getOperatingSystemIcon(),
            'os_version' => php_uname('r'),
            'os_build' => php_uname('v'),
            'architecture' => php_uname('m'),
            'hostname' => $hostname,
            'ip_address' => $ip,
            'city' => $city,
            'country' => $country,
            'country_code' => $net['country_code'] ?? null,
            'region' => $net['region'] ?? null,
            'timezone' => $browserTimezone ?: ($net['timezone'] ?? date_default_timezone_get()),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? (PHP_SAPI . ' / Laravel ' . app()->version()),
            'app_edition' => ($user && $user->isPro()) ? 'TunnelDeck Pro' : 'TunnelDeck Lite',
            'app_version' => '1.0.0',
            'hwid' => $hwid,
            'screen_resolution' => $screen,
            'user_agent' => $request ? $request->userAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? null),
            'reported_at' => now()->toIso8601String(),
        ];
    }

    /**
     * macOS helper: Read sw_vers product version.
     */
    protected function getMacProductVersion(): ?string
    {
        try {
            $output = @shell_exec('sw_vers -productVersion 2>/dev/null');
            $clean = trim((string) $output);
            if (!empty($clean)) {
                return $clean;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Linux helper: Read PRETTY_NAME or NAME from /etc/os-release.
     */
    protected function getLinuxDistroName(): ?string
    {
        $path = '/etc/os-release';
        if (file_exists($path) && is_readable($path)) {
            $content = (string) @file_get_contents($path);
            if (preg_match('/PRETTY_NAME="([^"]+)"/i', $content, $m)) {
                return $m[1];
            }
            if (preg_match('/NAME="([^"]+)"/i', $content, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}

