<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class BackgroundProcess extends Process
{
    /**
     * Start the process. On Windows, inject essential environment variables
     * if they are missing (e.g. USERPROFILE, HOMEDRIVE, HOMEPATH) to prevent
     * issues with cloudflared or other CLI tools.
     */
    public function start(?callable $callback = null, array $env = []): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $winEnv = [];
            foreach (['USERPROFILE', 'HOMEDRIVE', 'HOMEPATH', 'SystemRoot', 'SystemDrive', 'TEMP', 'TMP', 'PATH', 'APPDATA', 'LOCALAPPDATA', 'COMSPEC', 'windir'] as $var) {
                $val = getenv($var) ?: $_SERVER[$var] ?? $_ENV[$var] ?? null;
                if ($val) {
                    $winEnv[$var] = $val;
                }
            }

            // Fallback: construct USERPROFILE if missing but others are present
            if (empty($winEnv['USERPROFILE']) && !empty($winEnv['HOMEDRIVE']) && !empty($winEnv['HOMEPATH'])) {
                $winEnv['USERPROFILE'] = rtrim($winEnv['HOMEDRIVE'] . $winEnv['HOMEPATH'], '/\\');
            }

            // If still empty (e.g. running in custom IIS/Electron sandbox), use default Windows paths
            if (empty($winEnv['USERPROFILE'])) {
                $winEnv['USERPROFILE'] = 'C:\\Users\\dell i5 16gb';
                $winEnv['HOMEDRIVE'] = 'C:';
                $winEnv['HOMEPATH'] = '\\Users\\dell i5 16gb';
            }

            $env = array_merge($winEnv, $env);
        }

        parent::start($callback, $env);
    }

    /**
     * Override the destructor to prevent Symfony from terminating the background process
     * when the PHP script or request cycle finishes.
     */
    public function __destruct()
    {
        // By default, Symfony's Process destructor calls $this->stop(0) to terminate the subprocess.
        // Overriding it with an empty body allows the subprocess to survive when the parent script exits.
    }
}
