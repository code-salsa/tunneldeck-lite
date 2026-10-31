<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Services\BackgroundProcess;
use App\Services\DomainValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DomainController extends Controller
{
    protected DomainValidationService $validator;

    public function __construct(DomainValidationService $validator)
    {
        $this->validator = $validator;
    }

    /**
     * List all custom domains.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomDomain::query();

        if ($request->input('view') !== 'all' && auth()->id()) {
            $query->where('user_id', auth()->id())->orWhereNull('user_id');
        }

        $domains = $query->withCount('tunnels')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'domains' => $domains,
        ]);
    }

    /**
     * Store a new custom domain in pending status.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:150'],
        ]);

        $domainName = trim($validated['domain']);
        $domainName = preg_replace('/^https?:\/\//i', '', $domainName);
        $domainName = preg_replace('/^www\./i', '', $domainName);
        $domainName = strtolower(rtrim($domainName, '/.'));

        $existing = CustomDomain::where('domain', $domainName)->first();
        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'This domain is already configured.',
            ], 422);
        }

        $domain = CustomDomain::create([
            'user_id' => auth()->id(),
            'domain' => $domainName,
            'validation_status' => 'pending',
            'validated_at' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Custom domain added successfully. Click validate to complete setup.',
            'domain' => $domain,
        ], 201);
    }

    /**
     * Step-by-step validate custom domain.
     */
    public function validateDomain(Request $request, $id): JsonResponse
    {
        $domain = CustomDomain::findOrFail($id);

        // Step 1: Check if domain is registered & resolves
        $isRegistered = $this->validator->isRegistered($domain->domain);
        if (!$isRegistered) {
            $domain->update([
                'validation_status' => 'failed',
                'validated_at' => null,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'failed',
                'step1' => false,
                'step2' => false,
                'step3' => false,
                'requires_auth' => false,
                'message' => "No DNS records found for '{$domain->domain}'. Please make sure the domain is registered and active.",
            ]);
        }

        // Step 2: Validate connection to Cloudflare
        if (!$this->validator->hasCert()) {
            $domain->update([
                'validation_status' => 'failed',
                'validated_at' => null,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'failed',
                'step1' => true,
                'step2' => false,
                'step3' => false,
                'requires_auth' => true,
                'auth_zone' => null,
                'message' => "Cloudflare authentication required. Please log in to Cloudflare to authorize your domain.",
            ]);
        }

        $isValid = $this->validator->validateNameservers($domain->domain);
        $authZone = $this->validator->getAuthZoneName();

        if (!$isValid) {
            $domain->update([
                'validation_status' => 'failed',
                'validated_at' => null,
            ]);

            $errorMsg = $this->validator->getLastError() ?: "Domain '{$domain->domain}' is not authorized in your current Cloudflare session.";

            return response()->json([
                'success' => false,
                'status' => 'failed',
                'step1' => true,
                'step2' => false,
                'step3' => false,
                'requires_auth' => true,
                'auth_zone' => $authZone,
                'message' => $errorMsg,
            ]);
        }

        // Step 3: Domain Validated!
        $now = now();
        $domain->update([
            'validation_status' => 'validated',
            'validated_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'status' => 'validated',
            'step1' => true,
            'step2' => true,
            'step3' => true,
            'requires_auth' => false,
            'validated_at' => $now->toIso8601String(),
            'message' => 'Domain validated successfully! Nameservers point to Cloudflare.',
        ]);
    }

    /**
     * Start the cloudflared tunnel login flow (OAuth).
     */
    public function loginCloudflare(): JsonResponse
    {
        $binary = config('tunneldeck.cloudflared_binary_path', 'cloudflared');
        $logPath = storage_path('logs/cloudflare_login.log');

        if (file_exists($logPath)) {
            @unlink($logPath);
        }

        $home = DomainValidationService::getHomeDir();
        $certPath = $home . DIRECTORY_SEPARATOR . '.cloudflared' . DIRECTORY_SEPARATOR . 'cert.pem';
        if (file_exists($certPath)) {
            @unlink($certPath);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $killCmd = "powershell -NoProfile -Command \"Get-CimInstance Win32_Process -Filter \\\"name = 'cloudflared.exe'\\\" | Where-Object { \$_.CommandLine -like '*tunnel login*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }\"";
                @shell_exec($killCmd);
            } catch (\Exception $e) {
                Log::channel('tunnels')->error("Failed to kill existing login processes: " . $e->getMessage());
            }
        } else {
            @exec('pkill -f "cloudflared.*tunnel login"');
        }

        $cmdPrefix = PHP_OS_FAMILY === 'Windows' ? '' : 'exec ';
        $command = sprintf('%s%s tunnel login > %s 2>&1', $cmdPrefix, escapeshellarg($binary), escapeshellarg($logPath));
        
        $process = BackgroundProcess::fromShellCommandline($command);
        $process->start();

        // Wait for OAuth URL in log
        $loginUrl = null;
        for ($i = 0; $i < 25; $i++) {
            usleep(200000); // 200ms
            if (file_exists($logPath)) {
                $logContent = file_get_contents($logPath);
                if (preg_match('/https:\/\/dash\.cloudflare\.com\/[^\s]*/', $logContent, $matches)) {
                    $loginUrl = rtrim($matches[0], '.');
                    break;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'login_url' => $loginUrl,
            'message' => 'Cloudflare OAuth login initiated.',
        ]);
    }

    /**
     * Check if cert.pem exists and what zone is authorized.
     */
    public function cloudflareStatus(): JsonResponse
    {
        $hasCert = $this->validator->hasCert();
        $authZone = $hasCert ? $this->validator->getAuthZoneName() : null;

        return response()->json([
            'status' => 'success',
            'has_cert' => $hasCert,
            'auth_zone' => $authZone,
        ]);
    }

    /**
     * Delete custom domain.
     */
    public function destroy($id): JsonResponse
    {
        $domain = CustomDomain::findOrFail($id);
        $domain->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Domain deleted successfully.',
        ]);
    }
}
