<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\BytecodeObfuscatorService;
use App\Services\CodeIntegrityService;
use App\Services\HardwareFingerprintService;
use App\Services\LicenseSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BytecodeObfuscationTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCUwOo3s2MwWzMG\nW8Oj92KEo2RZnPmxScFI7WfP539y0cW6j7v5DahyWbUHs/BmuhlCWXO0lcBaB+9J\nqDCXKqLYknT4bCBjoys0qvfZ+fYuDX3z8oCCEaI01Ret5jOuPP/ap1B59TX9IK2R\nNIirb2qaQN1lwXdiEZAkP2+trqgClnxCaHbyLOm3AOH93lCBJo+Nkf94gSVK7jxu\nSesAqwXPoXUuoduxaT3jVpF8RrWj5UanyTmZNAmUocuHK3UmzHqX7GVkd5F9Lxqo\n3rihGW5qGpxfHLThIKmSxVVl/TCno9E08Qioj41ZsbFERhp/gqjrS7elhc4uuiL3\nGnLrhzr3AgMBAAECggEAGaFTud0yzfYoyIOvJeVOGER+2eHuM9ndb89X/76HeyUh\nyI9TeAoeZ/WE95rNZSXTjFcVVJwo1FphpHNSS/mQrXgup08aFZDbtOtwYSJshGrW\ntN2nvsGDDU9aESCE7xSbkPUbLKhPcRncThSbJ86+s21RuGKpS0BzcYtslqRtVmSM\nE5EltJemKweOaOaXQkGBkB+XI4hF7z6qRmTli80V1CxfwxExrZa25dcJUblj3DUt\nf66xATbOBcPWPnZ1FIq94WOBDdVh8/nRYLUiV9ZcRrYPcDoW9hqLSUvizOKZbZRT\n/gpCjZkzxqfbY6HElJyMnCC4MXmpCSAB3L+BVIepIQKBgQDOE+JfTHkTk6YI4m5K\n0WKHb+Zcv5SxWx02oc0GdURtRh72c3u/wUICJdtSJYvAQ/C/ExkPR2iFCvJoReHu\nD9+05Lvvew8P+V1Ur7S1iq1+v9ZPkHjriHtuXxhWOFuUOo1vx+WgD3dqRSidsE6T\nCqO2dFMhxcw82XzfSmGgfsj8QwKBgQC4ygS/pQl4T3qOAr+6wz7STbTm77W8xEZV\nNrJ898x8wmi+Cnmz3GAUu84mvwu4wwyFqC5KbHXWxmaowdxa282c46x14aUWYv9E\nDplkpCTtpVEg3UvARgVqRTJMsf9qi8oUePa4IXzyNHbktMSmmQcu2D6WAWrMH58i\njtsk2v31PQKBgE+xYNl5uVjYwFyvM/e7AV9e5rb3X4k2sxVtfNwCfXPEqjSkdDaz\nhdNO0IH0AxdXLsK1n/tIoiLRGO06GST2/ov16Lez3x9OT8lwKXILLwzVIwuh0yFC\npnkITWEKal/oRMn1g9diI8v08sAIblpuwuDMG1n0JAdkE+ZF3XszjIYBAoGBALGG\nmzNGKSflKzcdGvcgtcyyjJXEOtODuJIK/0Shh1lgJ+JTDAKoN8ITfwSoS2mQMQqC\nvR4QvtC8QEnmHH1JZ2pPLBL/2Wr9ZoEKiYEvVK7eQGeTUkAAxRkIKz+VTW0dqOEs\nKNJSkcfacVJc/AOVRlpRsybHa8N4qZg4p/3rSUl9AoGAbR1VuRJA1K1f/4x8hu1P\n1BQOwP1BLCjoC8NdaMXnFhnnr7tzxe450xs4TOtkPl26hYgD3NX2DUTcivJuOPzn\n6FC7+Luax2nX9GkRf8d7JEDUEC7OkrDpe9VUDi4JlWRbUvti00ni50qf08+W0iV5\nHPAoyqQKiDfFQMbRo6u14is=\n-----END PRIVATE KEY-----";

    protected function tearDown(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = true;
        CodeIntegrityService::$enforceInTesting = false;
        parent::tearDown();
    }

    protected function createSignedBundle(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $binarySignature = '';
        openssl_sign($json, $binarySignature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return [
            'certificate' => base64_encode($json),
            'signature' => base64_encode($binarySignature),
            'algorithm' => 'RSA-SHA256',
            'public_key' => config('tunneldeck.public_key'),
            'payload' => $payload,
        ];
    }

    public function test_bytecode_obfuscator_encrypts_strings_and_strips_comments(): void
    {
        $obfuscator = app(BytecodeObfuscatorService::class);

        $sampleCode = <<<'PHP'
<?php
/**
 * Developer secret comment that should be stripped.
 */
class SecureModule {
    // Single line secret comment
    public function getTier(): string {
        return 'pro';
    }
}
PHP;

        $obfuscated = $obfuscator->obfuscateCode($sampleCode);

        // Comments must be completely removed
        $this->assertStringNotContainsString('Developer secret comment', $obfuscated);
        $this->assertStringNotContainsString('Single line secret comment', $obfuscated);

        // Literal 'pro' string must be replaced with dynamic runtime decryptor
        $this->assertStringNotContainsString("'pro'", $obfuscated);
        $this->assertStringContainsString('BytecodeObfuscatorService::d(', $obfuscated);
    }

    public function test_runtime_deobfuscator_decrypts_strings_correctly(): void
    {
        $original = 'tunneldeck_license_super_secret_payload_2026';
        $encrypted = BytecodeObfuscatorService::encryptString($original);

        $this->assertNotEquals($original, $encrypted);
        $this->assertEquals($original, BytecodeObfuscatorService::d($encrypted));
    }

    public function test_code_integrity_service_generates_and_validates_manifest(): void
    {
        CodeIntegrityService::$enforceInTesting = true;

        $service = app(CodeIntegrityService::class);
        $manifest = $service->generateManifest();

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('signature', $manifest);
        $this->assertArrayHasKey('files', $manifest);
        $this->assertNotEmpty($manifest['files']);

        $verification = $service->verifyIntegrity();
        $this->assertTrue($verification['valid']);
        $this->assertEmpty($verification['tampered_files']);
    }

    public function test_code_integrity_detects_file_tampering(): void
    {
        CodeIntegrityService::$enforceInTesting = true;

        $service = app(CodeIntegrityService::class);
        $manifest = $service->generateManifest();

        // Simulate attacker tampering by altering an expected file hash in manifest
        $tamperedManifest = $manifest;
        $firstKey = array_key_first($tamperedManifest['files']);
        $tamperedManifest['files'][$firstKey] = str_repeat('a', 64); // Fake hash
        // Recompute HMAC to test hash mismatch
        $secret = config('app.key', 'TUNNELDECK_INTEGRITY_SALT_2026');
        $tamperedManifest['signature'] = hash_hmac('sha256', json_encode($tamperedManifest['files']), $secret);

        file_put_contents($service->getManifestPath(), json_encode($tamperedManifest));

        $verification = $service->verifyIntegrity();
        $this->assertFalse($verification['valid']);
        $this->assertContains($firstKey, $verification['tampered_files']);
    }

    public function test_layer_4_tampered_code_blocks_pro_license_entitlement(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = false;
        CodeIntegrityService::$enforceInTesting = true;

        $user = User::factory()->create([
            'email' => 'hacked@example.com',
            'package' => 'pro',
        ]);

        $hwid = app(HardwareFingerprintService::class)->getHwid();
        $bundle = $this->createSignedBundle([
            'email' => 'hacked@example.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'hwid' => $hwid,
            'license_key' => 'TD-PRO-TAMPERED-TEST',
            'issued_at' => time(),
        ]);

        Setting::set('license_certificate', $bundle['certificate']);
        Setting::set('license_signature', $bundle['signature']);

        // Mock an integrity violation
        $integrityMock = $this->createMock(CodeIntegrityService::class);
        $integrityMock->method('isIntegrityValid')->willReturn(false);
        $this->app->instance(CodeIntegrityService::class, $integrityMock);

        // Even with valid RSA signature and valid HWID, Layer 4 integrity violation denies Pro access
        $this->assertFalse($user->isPro());
    }

    public function test_obfuscate_artisan_command_executes_successfully(): void
    {
        $this->artisan('tunneldeck:obfuscate', ['--dry-run' => true])
            ->assertExitCode(0);
    }

    public function test_package_artisan_command_executes_successfully(): void
    {
        $testOut = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_package_' . uniqid() . '.zip';

        $this->artisan('tunneldeck:package', [
            '--out' => $testOut,
            '--no-obfuscate' => true,
        ])->assertExitCode(0);

        $this->assertFileExists($testOut);
        @unlink($testOut);
    }
}

