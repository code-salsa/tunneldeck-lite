<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\HardwareFingerprintService;
use App\Services\LicenseSignatureVerifier;
use App\Services\TunnelDeckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HardwareFingerprintTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCUwOo3s2MwWzMG\nW8Oj92KEo2RZnPmxScFI7WfP539y0cW6j7v5DahyWbUHs/BmuhlCWXO0lcBaB+9J\nqDCXKqLYknT4bCBjoys0qvfZ+fYuDX3z8oCCEaI01Ret5jOuPP/ap1B59TX9IK2R\nNIirb2qaQN1lwXdiEZAkP2+trqgClnxCaHbyLOm3AOH93lCBJo+Nkf94gSVK7jxu\nSesAqwXPoXUuoduxaT3jVpF8RrWj5UanyTmZNAmUocuHK3UmzHqX7GVkd5F9Lxqo\n3rihGW5qGpxfHLThIKmSxVVl/TCno9E08Qioj41ZsbFERhp/gqjrS7elhc4uuiL3\nGnLrhzr3AgMBAAECggEAGaFTud0yzfYoyIOvJeVOGER+2eHuM9ndb89X/76HeyUh\nyI9TeAoeZ/WE95rNZSXTjFcVVJwo1FphpHNSS/mQrXgup08aFZDbtOtwYSJshGrW\ntN2nvsGDDU9aESCE7xSbkPUbLKhPcRncThSbJ86+s21RuGKpS0BzcYtslqRtVmSM\nE5EltJemKweOaOaXQkGBkB+XI4hF7z6qRmTli80V1CxfwxExrZa25dcJUblj3DUt\nf66xATbOBcPWPnZ1FIq94WOBDdVh8/nRYLUiV9ZcRrYPcDoW9hqLSUvizOKZbZRT\n/gpCjZkzxqfbY6HElJyMnCC4MXmpCSAB3L+BVIepIQKBgQDOE+JfTHkTk6YI4m5K\n0WKHb+Zcv5SxWx02oc0GdURtRh72c3u/wUICJdtSJYvAQ/C/ExkPR2iFCvJoReHu\nD9+05Lvvew8P+V1Ur7S1iq1+v9ZPkHjriHtuXxhWOFuUOo1vx+WgD3dqRSidsE6T\nCqO2dFMhxcw82XzfSmGgfsj8QwKBgQC4ygS/pQl4T3qOAr+6wz7STbTm77W8xEZV\nNrJ898x8wmi+Cnmz3GAUu84mvwu4wwyFqC5KbHXWxmaowdxa282c46x14aUWYv9E\nDplkpCTtpVEg3UvARgVqRTJMsf9qi8oUePa4IXzyNHbktMSmmQcu2D6WAWrMH58i\njtsk2v31PQKBgE+xYNl5uVjYwFyvM/e7AV9e5rb3X4k2sxVtfNwCfXPEqjSkdDaz\nhdNO0IH0AxdXLsK1n/tIoiLRGO06GST2/ov16Lez3x9OT8lwKXILLwzVIwuh0yFC\npnkITWEKal/oRMn1g9diI8v08sAIblpuwuDMG1n0JAdkE+ZF3XszjIYBAoGBALGG\nmzNGKSflKzcdGvcgtcyyjJXEOtODuJIK/0Shh1lgJ+JTDAKoN8ITfwSoS2mQMQqC\nvR4QvtC8QEnmHH1JZ2pPLBL/2Wr9ZoEKiYEvVK7eQGeTUkAAxRkIKz+VTW0dqOEs\nKNJSkcfacVJc/AOVRlpRsybHa8N4qZg4p/3rSUl9AoGAbR1VuRJA1K1f/4x8hu1P\n1BQOwP1BLCjoC8NdaMXnFhnnr7tzxe450xs4TOtkPl26hYgD3NX2DUTcivJuOPzn\n6FC7+Luax2nX9GkRf8d7JEDUEC7OkrDpe9VUDi4JlWRbUvti00ni50qf08+W0iV5\nHPAoyqQKiDfFQMbRo6u14is=\n-----END PRIVATE KEY-----";

    protected function tearDown(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = true;
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

    public function test_hwid_generation_format_and_persistence(): void
    {
        $service = app(HardwareFingerprintService::class);
        $hwid1 = $service->getHwid();

        $this->assertMatchesRegularExpression('/^TD-HWID-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $hwid1);

        // Calling it again returns identical cached HWID
        $hwid2 = $service->getHwid();
        $this->assertEquals($hwid1, $hwid2);

        // Setting table stores the device HWID
        $this->assertEquals($hwid1, Setting::get('device_hwid'));
    }

    public function test_hwid_device_details_contains_platform_and_architecture(): void
    {
        $service = app(HardwareFingerprintService::class);
        $details = $service->getDeviceDetails();

        $this->assertArrayHasKey('hwid', $details);
        $this->assertArrayHasKey('platform', $details);
        $this->assertArrayHasKey('hostname', $details);
        $this->assertArrayHasKey('os_version', $details);
        $this->assertArrayHasKey('architecture', $details);
        $this->assertEquals($service->getHwid(), $details['hwid']);
    }

    public function test_tunneldeck_service_attaches_hwid_header_to_api_requests(): void
    {
        $service = app(HardwareFingerprintService::class);
        $currentHwid = $service->getHwid();

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*' => function (\Illuminate\Http\Client\Request $request) use ($currentHwid) {
                $this->assertTrue($request->hasHeader('X-DEVICE-HWID'));
                $this->assertEquals($currentHwid, $request->header('X-DEVICE-HWID')[0]);

                return Http::response([
                    'success' => true,
                    'data' => [
                        'package' => 'pro',
                    ],
                ], 200);
            },
        ]);

        $tunnelDeck = app(TunnelDeckService::class);
        $tunnelDeck->getUser('test@example.com');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($currentHwid) {
            return $request->hasHeader('X-DEVICE-HWID', $currentHwid);
        });
    }

    public function test_layer_3_rejects_certificate_bound_to_different_hwid(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = false;

        $user = User::factory()->create([
            'email' => 'victim@example.com',
            'package' => 'pro',
        ]);

        // Certificate was legally issued to victim@example.com, but on a DIFFERENT machine (HWID: TD-HWID-DIFF-1111-2222-3333)
        $bundle = $this->createSignedBundle([
            'email' => 'victim@example.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'hwid' => 'TD-HWID-DIFF-1111-2222-3333',
            'license_key' => 'TD-PRO-PIRATED-01',
            'issued_at' => time(),
        ]);

        // Attempting to store or activate this certificate on local machine must be rejected
        $verifier = app(LicenseSignatureVerifier::class);
        $stored = $verifier->storeSignedLicense($bundle, $user);
        $this->assertFalse($stored);

        // Even if attacker manually inserted it into settings
        Setting::set('license_certificate', $bundle['certificate']);
        Setting::set('license_signature', $bundle['signature']);

        // User isPro() must evaluate false because of HWID mismatch (anti-cloning protection)
        $this->assertFalse($user->isPro());
    }

    public function test_layer_3_accepts_certificate_bound_to_current_hwid(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = false;

        $user = User::factory()->create([
            'email' => 'licensed@example.com',
            'package' => 'pro',
        ]);

        $hwidService = app(HardwareFingerprintService::class);
        $currentHwid = $hwidService->getHwid();

        // Certificate issued specifically for this device's HWID
        $bundle = $this->createSignedBundle([
            'email' => 'licensed@example.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'hwid' => $currentHwid,
            'license_key' => 'TD-PRO-LEGIT-HWID',
            'issued_at' => time(),
        ]);

        $verifier = app(LicenseSignatureVerifier::class);
        $stored = $verifier->storeSignedLicense($bundle, $user);

        $this->assertTrue($stored);
        $this->assertTrue($user->isPro());
        $this->assertEquals(50, $user->maxTunnels());
    }

    public function test_device_info_endpoint_returns_json_details(): void
    {
        $user = User::factory()->create([
            'email' => 'tester@example.com',
            'package' => 'lite',
        ]);

        $response = $this->actingAs($user)->getJson('/api/license/device-info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'device' => [
                'hwid',
                'platform',
                'hostname',
                'os_version',
                'architecture',
            ],
            'is_pro_verified',
        ]);

        $hwid = app(HardwareFingerprintService::class)->getHwid();
        $this->assertEquals($hwid, $response->json('device.hwid'));
    }
}

