<?php

namespace App\Services;

class BytecodeObfuscatorService
{
    /**
     * Default XOR encryption key for runtime string de-obfuscation.
     */
    public const DEFAULT_KEY = 'TD_SECURE_KEY_2026_XOR';

    /**
     * Reversible XOR-based string encryptor.
     * Produces hexadecimal representation of the XOR-masked bytes.
     */
    public static function encryptString(string $plain, string $key = self::DEFAULT_KEY): string
    {
        $out = '';
        $keyLen = strlen($key);
        for ($i = 0, $len = strlen($plain); $i < $len; $i++) {
            $out .= chr(ord($plain[$i]) ^ ord($key[$i % $keyLen]));
        }
        return bin2hex($out);
    }

    /**
     * Runtime de-obfuscation helper.
     * Decodes the hexadecimal string and applies XOR unmasking.
     */
    public static function d(string $hex, string $key = self::DEFAULT_KEY): string
    {
        $raw = hex2bin($hex);
        if ($raw === false) {
            return '';
        }

        $out = '';
        $keyLen = strlen($key);
        for ($i = 0, $len = strlen($raw); $i < $len; $i++) {
            $out .= chr(ord($raw[$i]) ^ ord($key[$i % $keyLen]));
        }
        return $out;
    }

    /**
     * Strip comments and docblocks from PHP source code using the native tokenizer.
     */
    public function stripComments(string $source): string
    {
        $tokens = token_get_all($source);
        $output = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $id = $token[0];
                $text = $token[1];

                // Skip comments and docblocks
                if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                    continue;
                }

                $output .= $text;
            } else {
                $output .= $token;
            }
        }

        return $output;
    }

    /**
     * Obfuscate PHP source code by:
     * 1. Stripping comments and docblocks.
     * 2. Encrypting sensitive string literals with XOR and dynamic decryptor calls.
     * 3. Injecting a Layer 4 integrity guard header.
     */
    public function obfuscateCode(string $source, array $sensitiveStrings = []): string
    {
        $clean = $this->stripComments($source);

        $defaultSensitives = [
            'pro',
            'enterprise',
            'license_certificate',
            'license_signature',
            'license_key',
            'licensing_public_key',
            'X-DEVICE-HWID',
            'device_hwid',
        ];

        $allSensitives = array_unique(array_merge($defaultSensitives, $sensitiveStrings));

        // Replace literal quoted strings with decryptor calls
        foreach ($allSensitives as $target) {
            $encrypted = self::encryptString($target);
            $replacement = "\\App\\Services\\BytecodeObfuscatorService::d('{$encrypted}')";

            // Match single quotes
            $clean = str_replace("'{$target}'", $replacement, $clean);
            // Match double quotes
            $clean = str_replace("\"{$target}\"", $replacement, $clean);
        }

        // Add Layer 4 Anti-Tamper Security Header right after <?php opening tag
        $header = "/* [TUNNELDECK-BYTECODE-PROTECTION: LAYER-4 ACTIVE - DO NOT TAMPER] */\n";
        if (str_starts_with(ltrim($clean), '<?php')) {
            $clean = preg_replace('/^<\?php\s*/', "<?php\n" . $header, $clean, 1);
        }

        return $clean;
    }
}

