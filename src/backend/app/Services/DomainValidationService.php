<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DomainValidationService
{
    /**
     * Store the last validation error message.
     *
     * @var string|null
     */
    protected $lastError = null;

    /**
     * Get the last validation error message.
     *
     * @return string|null
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Validate that the domain's nameservers point to Cloudflare and is authorized in this session.
     *
     * @param string $domain
     * @return bool
     */
    public function validateNameservers(string $domain): bool
    {
        $this->lastError = null;

        if (empty($domain)) {
            $this->lastError = 'Domain name cannot be empty.';
            return false;
        }

        // Clean up the domain name (remove whitespace, trailing dots, protocol if accidentally added)
        $domain = trim($domain);
        $domain = preg_replace('/^https?:\/\//i', '', $domain);
        $domain = preg_replace('/^www\./i', '', $domain);
        $domain = rtrim($domain, '/.');

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            Log::channel('tunnels')->warning("DomainValidationService: Invalid domain format: {$domain}");
            $this->lastError = 'Invalid domain name format.';
            return false;
        }

        // For local development or testing domains (e.g. .local, .test, localhost), bypass validation
        if (preg_match('/\.local$/i', $domain) || preg_match('/\.test$/i', $domain) || $domain === 'localhost') {
            Log::channel('tunnels')->info("DomainValidationService: Bypassing nameserver check for local/testing domain: {$domain}");
            return true;
        }

        try {
            // Get NS records for the domain
            $records = @dns_get_record($domain, DNS_NS);

            if (empty($records) || !is_array($records)) {
                Log::channel('tunnels')->warning("DomainValidationService: No NS records found for domain: {$domain}");
                $this->lastError = "No NS (nameserver) records found for '{$domain}'. Please make sure the domain is registered and active.";
                return false;
            }

            $hasCfNs = false;
            foreach ($records as $record) {
                if (isset($record['target'])) {
                    $ns = strtolower($record['target']);
                    // Cloudflare nameservers end with .cloudflare.com (or .cloudflare.com. with trailing dot)
                    if (str_ends_with($ns, '.cloudflare.com') || str_ends_with($ns, '.cloudflare.com.')) {
                        $hasCfNs = true;
                        break;
                    }
                }
            }

            if (!$hasCfNs) {
                Log::channel('tunnels')->warning("DomainValidationService: Nameservers for domain {$domain} do not point to Cloudflare.");
                $this->lastError = "The nameservers for '{$domain}' do not point to Cloudflare. Please delegate your domain to Cloudflare first.";
                return false;
            }

            Log::channel('tunnels')->info("DomainValidationService: Nameserver check passed for {$domain}. Verifying Cloudflare zone ownership...");

            // Verify domain zone ownership with logged in session
            if (!$this->hasCert()) {
                $this->lastError = 'Cloudflare credentials not found. Please authenticate with Cloudflare first.';
                return false;
            }

            $authZone = $this->getAuthZoneName();
            if (!$authZone) {
                $this->lastError = 'Failed to retrieve authorized zone name from Cloudflare API. Please check your credentials or network connection.';
                return false;
            }

            $domainLower = strtolower($domain);
            if ($domainLower !== $authZone && !str_ends_with($domainLower, '.' . $authZone)) {
                Log::channel('tunnels')->warning("DomainValidationService: Domain {$domain} is not authorized in current Cloudflare session. Authorized zone: {$authZone}");
                $this->lastError = "Domain '{$domain}' is not authorized in your current Cloudflare session. Your authenticated Cloudflare zone domain is '{$authZone}'. Please login again and select the correct domain.";
                return false;
            }

            Log::channel('tunnels')->info("DomainValidationService: Validated domain {$domain} matches Cloudflare zone {$authZone}");
            return true;

        } catch (\Exception $e) {
            Log::channel('tunnels')->error("DomainValidationService: Error validating nameservers for {$domain}: " . $e->getMessage());
            $this->lastError = 'An error occurred during Cloudflare validation: ' . $e->getMessage();
        }

        return false;
    }

    /**
     * Get the domain name of the zone authorized by the Cloudflare certificate.
     *
     * @return string|null
     */
    public function getAuthZoneName(): ?string
    {
        $home = self::getHomeDir();
        $certPath = $home . DIRECTORY_SEPARATOR . '.cloudflared' . DIRECTORY_SEPARATOR . 'cert.pem';
        
        if (!file_exists($certPath)) {
            return null;
        }

        $content = file_get_contents($certPath);
        if (preg_match('/-----BEGIN ARGO TUNNEL TOKEN-----\s+(.*?)\s+-----END ARGO TUNNEL TOKEN-----/s', $content, $matches)) {
            $tokenBase64 = str_replace(["\r", "\n", " "], "", $matches[1]);
            $tokenJson = base64_decode($tokenBase64);
            $token = json_decode($tokenJson, true);
            
            if (isset($token['zoneID'], $token['apiToken'])) {
                $zoneId = $token['zoneID'];
                $apiToken = $token['apiToken'];
                
                try {
                    $ch = curl_init("https://api.cloudflare.com/client/v4/zones/{$zoneId}");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer {$apiToken}",
                        "Content-Type: application/json"
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if (!empty($response)) {
                        $data = json_decode($response, true);
                        if (isset($data['success']) && $data['success'] && isset($data['result']['name'])) {
                            return strtolower($data['result']['name']);
                        }
                        
                        // Check for invalid token errors
                        if (isset($data['errors']) && is_array($data['errors'])) {
                            foreach ($data['errors'] as $err) {
                                if (isset($err['code']) && ($err['code'] === 9109 || $err['code'] === 6003 || $err['code'] === 9103)) {
                                    Log::channel('tunnels')->warning("DomainValidationService: Cloudflare API auth error {$err['code']}: {$err['message']}. Deleting invalid certificate.");
                                    if (file_exists($certPath)) {
                                        @unlink($certPath);
                                    }
                                    break;
                                }
                            }
                        }
                    }
                    
                    if ($httpCode === 403 || $httpCode === 401) {
                        Log::channel('tunnels')->warning("DomainValidationService: Cloudflare API returned HTTP {$httpCode}. Deleting invalid certificate.");
                        if (file_exists($certPath)) {
                            @unlink($certPath);
                        }
                    }
                } catch (\Exception $e) {
                    Log::channel('tunnels')->error("DomainValidationService: Failed to fetch zone info from Cloudflare API: " . $e->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * Check if the Cloudflare CLI certificate exists.
     *
     * @return bool
     */
    public function hasCert(): bool
    {
        $home = self::getHomeDir();
        return file_exists($home . DIRECTORY_SEPARATOR . '.cloudflared' . DIRECTORY_SEPARATOR . 'cert.pem');
    }

    /**
     * Check if a domain is registered and has active DNS records.
     *
     * @param string $domain
     * @return bool
     */
    public function isRegistered(string $domain): bool
    {
        if (empty($domain)) {
            return false;
        }

        // Clean up the domain
        $domain = trim($domain);
        $domain = preg_replace('/^https?:\/\//i', '', $domain);
        $domain = preg_replace('/^www\./i', '', $domain);
        $domain = rtrim($domain, '/.');

        // For testing domains (e.g. .local, .test, localhost), bypass registration validation
        if (preg_match('/\.local$/i', $domain) || preg_match('/\.test$/i', $domain) || $domain === 'localhost') {
            return true;
        }

        // Try to resolve DNS records for this domain to check if it's registered
        $records = @dns_get_record($domain, DNS_NS);
        if (!empty($records) && is_array($records)) {
            return true;
        }

        return (bool) @checkdnsrr($domain, 'ANY');
    }

    /**
     * Get user's home directory in a cross-platform way.
     */
    public static function getHomeDir(): string
    {
        $home = getenv('HOME');
        if (!empty($home)) {
            return rtrim($home, '/\\');
        }
        $userProfile = getenv('USERPROFILE');
        if (!empty($userProfile)) {
            return rtrim($userProfile, '/\\');
        }
        $homeDrive = getenv('HOMEDRIVE');
        $homePath = getenv('HOMEPATH');
        if (!empty($homeDrive) && !empty($homePath)) {
            return rtrim($homeDrive . $homePath, '/\\');
        }
        return '/Users/apple'; // Fallback
    }
}
