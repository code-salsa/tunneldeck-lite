<?php

namespace App\Services;

class EmailValidator
{
    /**
     * Common free email providers.
     */
    protected static array $freeDomains = [
        'gmail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'hotmail.co.uk', 
        'outlook.com', 'live.com', 'msn.com', 'icloud.com', 'aol.com', 'mail.com', 
        'protonmail.com', 'proton.me', 'zoho.com', 'gmx.com', 'yandex.com', 
        'mail.ru', 'rambler.ru', 'fastmail.com', 'hushmail.com'
    ];

    /**
     * Cached/compiled list of disposable domains.
     */
    protected static ?array $cachedDisposableDomains = null;

    /**
     * Get compiled disposable email domains.
     */
    protected static function getDisposableDomains(): array
    {
        if (self::$cachedDisposableDomains === null) {
            $path = storage_path('app/disposable_domains.php');
            if (file_exists($path)) {
                self::$cachedDisposableDomains = require $path;
            } else {
                // Fallback list as key-value pairs for O(1) isset() lookups
                $fallback = [
                    'mailinator.com' => true,
                    '10minutemail.com' => true,
                    'temp-mail.org' => true,
                    'tempmail.com' => true,
                    'yopmail.com' => true,
                    'guerrillamail.com' => true,
                    'sharklasers.com' => true,
                    'dispostable.com' => true,
                    'maildrop.cc' => true,
                    'jctoto.com' => true,
                ];
                self::$cachedDisposableDomains = $fallback;
            }
        }
        return self::$cachedDisposableDomains;
    }

    /**
     * Normalize an email address by stripping subaddressing/plus addressing.
     * E.g., user+1@gmail.com -> user@gmail.com
     *
     * @param string $email
     * @return string
     */
    public static function normalize(string $email): string
    {
        $email = trim(strtolower($email));
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $local = $parts[0];
        $domain = $parts[1];

        // Remove plus addressing part
        $local = explode('+', $local)[0];

        return $local . '@' . $domain;
    }

    /**
     * Validate an email address based on account type.
     *
     * @param string $email
     * @param string $type 'individual' or 'agency'
     * @return array Array with ['valid' => bool, 'message' => string]
     */
    public static function validate(string $email, string $type = 'individual'): array
    {
        // 1. Basic format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'The email address format is invalid.'
            ];
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return [
                'valid' => false,
                'message' => 'The email address format is invalid.'
            ];
        }

        $local = $parts[0];
        $domain = strtolower($parts[1]);

        // 1.5. Plus-addressing check
        if (str_contains($local, '+')) {
            return [
                'valid' => false,
                'message' => 'Plus-addressed email aliases (containing "+") are not allowed.'
            ];
        }

        // 2. Disposable email check (disallowed for both)
        $disposable = self::getDisposableDomains();
        if (isset($disposable[$domain])) {
            return [
                'valid' => false,
                'message' => 'Temporary or disposable email addresses are not allowed.'
            ];
        }

        // 3. Free email check for agencies
        if ($type === 'agency') {
            if (in_array($domain, self::$freeDomains)) {
                return [
                    'valid' => false,
                    'message' => 'Agencies must sign up with a valid business email. Free email accounts (like Gmail or Yahoo) are not allowed.'
                ];
            }
        }

        // 4. API check for disposable and general validation (skip in tests)
        if (!app()->runningUnitTests()) {
            try {
                // Call mailcheck.ai with a 2-second timeout to avoid blocking
                $response = \Illuminate\Support\Facades\Http::timeout(2)
                    ->get("https://api.mailcheck.ai/email/{$email}");

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['disposable']) && $data['disposable'] === true) {
                        return [
                            'valid' => false,
                            'message' => 'Temporary or disposable email addresses are not allowed.'
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Fallback to DNS records check if API fails or times out
            }

            // 5. DNS check for domain validity
            $hasMx = checkdnsrr($domain, 'MX');
            $hasA = checkdnsrr($domain, 'A');
            if (!$hasMx && !$hasA) {
                return [
                    'valid' => false,
                    'message' => 'The email domain is invalid or does not have active DNS records.'
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'Email is valid.'
        ];
    }
}
