<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflared Binary Path
    |--------------------------------------------------------------------------
    |
    | This is the path to the cloudflared binary on your host machine.
    | By default, we assume it is installed globally and available in the PATH.
    |
    */
    'cloudflared_binary_path' => (function() {
        $envPath = env('CLOUDFLARED_BINARY_PATH');
        if ($envPath) {
            return $envPath;
        }

        // 1. Check project bundled binaries
        if (PHP_OS_FAMILY === 'Windows') {
            $bundledWin = base_path('bin/windows/x64/cloudflared.exe');
            if (file_exists($bundledWin)) {
                return $bundledWin;
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $arch = php_uname('m');
            $macSubdir = str_contains($arch, 'arm') ? 'mac/arm64' : 'mac/x64';
            foreach ([$macSubdir, 'mac/intel', 'mac/arm64', 'mac/x64'] as $subdir) {
                $bundledMac = base_path('bin/' . $subdir . '/cloudflared');
                if (file_exists($bundledMac) && is_executable($bundledMac)) {
                    return $bundledMac;
                }
            }
        }

        // 2. Check standard global paths (macOS/Linux fallback)
        foreach (['/opt/homebrew/bin/cloudflared', '/usr/local/bin/cloudflared'] as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // 3. Default fallback to global environment PATH
        return 'cloudflared';
    })(),

    /*
    |--------------------------------------------------------------------------
    | Process Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum time in seconds to wait for a tunnel to boot and output
    | a trycloudflare public URL before timing out.
    |
    */
    'process_timeout' => env('CLOUDFLARED_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Process Monitor Interval
    |--------------------------------------------------------------------------
    |
    | The interval (in seconds) for the background monitor.
    |
    */
    'process_monitor_interval' => 60,

    /*
    |--------------------------------------------------------------------------
    | Optional Auto Restart
    |--------------------------------------------------------------------------
    |
    | If enabled, when the background monitor detects a running tunnel has
    | crashed or failed its health check, it will attempt to restart it.
    |
    */
    'auto_restart' => env('CLOUDFLARED_AUTO_RESTART', true),

    /*
    |--------------------------------------------------------------------------
    | Maximum Tunnels Limit
    |--------------------------------------------------------------------------
    |
    | The maximum number of tunnel configurations allowed to be created
    | in the dashboard to prevent resource exhaustion.
    |
    */
    'max_tunnels' => env('CLOUDFLARED_MAX_TUNNELS', 5),

    /*
    |--------------------------------------------------------------------------
    | Licensing Authority RSA Public Key (Cryptographic Asymmetric Verification)
    |--------------------------------------------------------------------------
    |
    | The official RSA 2048-bit public key used to verify digital license
    | certificates issued by the licensing server (licensing.tunneldeck.online).
    | Prevents manual SQLite database tampering.
    |
    */
    'public_key' => env('TUNNELDECK_PUBLIC_KEY', "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlMDqN7NjMFszBlvDo/di\nhKNkWZz5sUnBSO1nz+d/ctHFuo+7+Q2oclm1B7PwZroZQllztJXAWgfvSagwlyqi\n2JJ0+GwgY6MrNKr32fn2Lg198/KAghGiNNUXreYzrjz/2qdQefU1/SCtkTSIq29q\nmkDdZcF3YhGQJD9vra6oApZ8Qmh28izptwDh/d5QgSaPjZH/eIElSu48bknrAKsF\nz6F1LqHbsWk941aRfEa1o+VGp8k5mTQJlKHLhyt1Jsx6l+xlZHeRfS8aqN64oRlu\nahqcXxy04SCpksVVZf0wp6PRNPEIqI+NWbGxREYaf4Ko60u3pYXOLroi9xpy64c6\n9wIDAQAB\n-----END PUBLIC KEY-----"),

];
