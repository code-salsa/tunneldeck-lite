<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseSignatureTest extends TestCase
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

    public function test_license_signature_verifier_validates_legitimate_certificate(): void
    {
        $verifier = app(LicenseSignatureVerifier::class);

        $bundle = $this->createSignedBundle([
            'email' => 'client@test.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'is_unlimited_tunnels' => true,
            'license_key' => 'TD-PRO-TEST-VALID-01',
            'issued_at' => time(),
        ]);

        $verified = $verifier->verify($bundle['certificate'], $bundle['signature']);

        $this->assertIsArray($verified);
        $this->assertEquals('client@test.com', $verified['email']);
        $this->assertEquals('pro', $verified['package']);
        $this->assertEquals('TD-PRO-TEST-VALID-01', $verified['license_key']);
    }

    public function test_tampered_certificate_is_rejected(): void
    {
        $verifier = app(LicenseSignatureVerifier::class);

        $bundle = $this->createSignedBundle([
            'email' => 'client@test.com',
            'package' => 'lite',
            'tier' => 'Lite',
            'license_key' => 'TD-LITE-TEST',
            'issued_at' => time(),
        ]);

        // Malicious actor modifies payload inside certificate to "pro" without private key
        $tamperedJson = json_encode([
            'email' => 'client@test.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'license_key' => 'TD-LITE-TEST',
            'issued_at' => time(),
        ]);
        $tamperedCertificate = base64_encode($tamperedJson);

        // Verification must fail because signature was computed on original payload
        $result = $verifier->verify($tamperedCertificate, $bundle['signature']);

        $this->assertFalse($result);
    }

    public function test_anti_tamper_rejects_unauthorized_database_pro_package(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = false;

        $user = User::factory()->create([
            'email' => 'hacker@test.com',
            'package' => 'pro', // Hacker set package='pro' directly in SQLite!
        ]);

        // Setting has NO valid certificate / signature
        Setting::forget('license_certificate');
        Setting::forget('license_signature');

        $this->assertFalse($user->isPro());
        $this->assertEquals(10, $user->maxTunnels());
    }

    public function test_anti_tamper_rejects_certificate_issued_for_different_user(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = false;

        $user = User::factory()->create([
            'email' => 'victim@test.com',
            'package' => 'pro',
        ]);

        // Attacker copies a valid certificate signed for someone else (attacker@test.com)
        $bundle = $this->createSignedBundle([
            'email' => 'attacker@test.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'license_key' => 'TD-PRO-ATTACKER',
            'issued_at' => time(),
        ]);

        Setting::set('license_certificate', $bundle['certificate']);
        Setting::set('license_signature', $bundle['signature']);

        // User email (victim@test.com) does not match signed email (attacker@test.com)
        $this->assertFalse($user->isPro());
    }

    public function test_anti_tamper_accepts_valid_certificate_issued_for_user(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = false;

        $user = User::factory()->create([
            'email' => 'legit@test.com',
            'package' => 'pro',
        ]);

        $bundle = $this->createSignedBundle([
            'email' => 'legit@test.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'license_key' => 'TD-PRO-LEGIT',
            'issued_at' => time(),
        ]);

        $verifier = app(LicenseSignatureVerifier::class);
        $stored = $verifier->storeSignedLicense($bundle, $user);

        $this->assertTrue($stored);
        $this->assertTrue($user->isPro());
        $this->assertEquals(50, $user->maxTunnels());
        $this->assertEquals('TD-PRO-LEGIT', Setting::get('license_key'));
    }

    public function test_license_sync_stores_signed_license_from_cloud(): void
    {
        LicenseSignatureVerifier::$bypassInTesting = false;

        $user = User::factory()->create([
            'email' => 'subscriber@test.com',
            'package' => 'lite',
        ]);

        $bundle = $this->createSignedBundle([
            'email' => 'subscriber@test.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'license_key' => 'TD-PRO-SYNCED',
            'issued_at' => time(),
        ]);

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*' => Http::response([
                'success' => true,
                'data' => [
                    'package' => 'pro',
                    'currency' => 'INR',
                    'signed_license' => $bundle,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/license/sync');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'package' => 'pro',
        ]);

        $this->assertEquals('pro', $user->fresh()->package);
        $this->assertTrue($user->fresh()->isPro());
        $this->assertEquals($bundle['certificate'], Setting::get('license_certificate'));
        $this->assertEquals($bundle['signature'], Setting::get('license_signature'));
        $this->assertEquals('TD-PRO-SYNCED', Setting::get('license_key'));
    }
}

