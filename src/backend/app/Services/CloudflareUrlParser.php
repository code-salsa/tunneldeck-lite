<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CloudflareUrlParser
{
    /**
     * Regex pattern to match the TryCloudflare public URL.
     */
    protected const URL_REGEX = '/https:\/\/[a-zA-Z0-9-]+\.trycloudflare\.com/';

    /**
     * Scan the log file and extract the TryCloudflare URL if present.
     */
    public function extractUrl(string $logPath): ?string
    {
        if (!file_exists($logPath)) {
            return null;
        }

        try {
            $content = file_get_contents($logPath);
            if (empty($content)) {
                return null;
            }

            // Perform regular expression match
            if (preg_match(self::URL_REGEX, $content, $matches)) {
                $url = trim($matches[0]);
                Log::channel('tunnels')->info("CloudflareUrlParser: Extracted public URL: {$url}");
                return $url;
            }
        } catch (\Exception $e) {
            Log::channel('tunnels')->error("CloudflareUrlParser: Error reading log file {$logPath}: " . $e->getMessage());
        }

        return null;
    }
}
