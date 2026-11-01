<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Tunnel;
use App\Models\User;
use App\Services\LicenseSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoSyncAndSuspensionTest extends TestCase
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

    public function test_auto_sync_upgrades_user_when_licensing_server_returns_pro(): void
    {
        $user = User::factory()->create([
            'email' => 'client@enterprise.com',
            'package' => 'lite',
            'status' => 'active',
        ]);

        $bundle = $this->createSignedBundle([
            'email' => 'client@enterprise.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'status' => 'active',
            'license_key' => 'TD-PRO-UPGRADE-SYNC',
            'issued_at' => time(),
        ]);

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*' => Http::response([
                'success' => true,
                'data' => [
                    'email' => 'client@enterprise.com',
                    'package' => 'pro',
                    'status' => 'active',
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
            'upgraded' => true,
            'suspended' => false,
        ]);

        $this->assertEquals('pro', $user->fresh()->package);
        $this->assertEquals('active', $user->fresh()->status);
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'description' => 'Plan upgraded from lite to pro via licensing server sync',
        ]);
    }

    public function test_auto_sync_suspends_user_when_licensing_server_returns_suspended(): void
    {
        $user = User::factory()->create([
            'email' => 'badactor@example.com',
            'package' => 'pro',
            'status' => 'active',
        ]);

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*' => Http::response([
                'success' => true,
                'data' => [
                    'email' => 'badactor@example.com',
                    'package' => 'pro',
                    'status' => 'suspended',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/license/sync');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'suspended' => true,
            'status' => 'suspended',
        ]);

        $this->assertEquals('suspended', $user->fresh()->status);
        $this->assertTrue($user->fresh()->isSuspended());
        $this->assertFalse($user->fresh()->isActive());

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'description' => 'Account access suspended by licensing administrator.',
        ]);
    }

    public function test_suspended_user_is_redirected_to_suspended_page_on_web(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('account.suspended'));
    }

    public function test_suspended_user_is_blocked_with_403_on_api(): void
    {
        $user = User::factory()->create([
            'email' => 'blocked@example.com',
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($user)->getJson('/api/tunnels');

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'ACCOUNT_SUSPENDED',
            'suspended' => true,
        ]);
    }

    public function test_suspended_user_can_access_suspended_page(): void
    {
        $user = User::factory()->create([
            'email' => 'view@example.com',
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($user)->get('/suspended');

        $response->assertStatus(200);
        $response->assertSee('Application Access Suspended');
        $response->assertSee('view@example.com');
    }

    public function test_re_activating_user_on_licensing_server_restores_active_status(): void
    {
        $user = User::factory()->create([
            'email' => 'reformed@example.com',
            'package' => 'lite',
            'status' => 'suspended',
        ]);

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*' => Http::response([
                'success' => true,
                'data' => [
                    'email' => 'reformed@example.com',
                    'package' => 'lite',
                    'status' => 'active',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/license/sync');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'active',
            'suspended' => false,
        ]);

        $this->assertEquals('active', $user->fresh()->status);
        $this->assertTrue($user->fresh()->isActive());

        // User can now access dashboard again
        $webResponse = $this->actingAs($user)->get('/');
        $webResponse->assertStatus(200);
    }

    public function test_auto_sync_handles_activation_limit_reached_and_locks_pro_features(): void
    {
        $user = User::factory()->create([
            'email' => 'seats_exceeded@example.com',
            'package' => 'pro',
            'status' => 'active',
        ]);

        \App\Models\Setting::set('license_certificate', 'dummy_cert');
        \App\Models\Setting::set('license_signature', 'dummy_sig');

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*' => Http::response([
                'success' => true,
                'data' => [
                    'email' => 'seats_exceeded@example.com',
                    'package' => 'lite',
                    'status' => 'activation_limit_reached',
                    'max_activations' => 2,
                    'activations_count' => 2,
                    'available_seats' => 0,
                    'activation_limit_reached' => true,
                    'activation' => [
                        'limit_reached' => true,
                        'max_activations' => 2,
                        'activations_count' => 2,
                        'available_seats' => 0,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/license/sync');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'activation_limit_reached' => true,
            'max_activations' => 2,
            'activations_count' => 2,
            'package' => 'lite',
        ]);

        $this->assertEquals('lite', $user->fresh()->package);
        $this->assertNull(\App\Models\Setting::get('license_certificate'));
        $this->assertNull(\App\Models\Setting::get('license_signature'));

        // Activity logged
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'type' => 'warning',
        ]);
    }

    public function test_seats_info_endpoint_returns_seats_and_hwid_details(): void
    {
        $user = User::factory()->create([
            'email' => 'seats_user@example.com',
        ]);

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*/activations' => Http::response([
                'success' => true,
                'max_activations' => 5,
                'activations_count' => 2,
                'available_seats' => 3,
                'bound_hwids' => ['TD-HWID-AAAA', 'TD-HWID-BBBB'],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/license/seats');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'max_activations' => 5,
            'activations_count' => 2,
            'available_seats' => 3,
        ]);
        $this->assertArrayHasKey('local_hwid', $response->json());
    }

    public function test_frontend_occurrences_switch_from_lite_to_pro_when_user_is_pro(): void
    {
        // 1. Lite user sees "Lite" across frontend
        $liteUser = User::factory()->create([
            'email' => 'regular@tunneldeck.com',
            'package' => 'lite',
            'status' => 'active',
        ]);

        $liteResponse = $this->actingAs($liteUser)->get(route('dashboard'));
        $liteResponse->assertStatus(200);
        $liteResponse->assertSee('<span id="brand-edition-badge"', false);
        $liteResponse->assertSee('>Lite</span>', false);
        $liteResponse->assertSee('Dashboard — TunnelDeck Lite', false);

        // 2. Pro user sees "Pro" dynamically across badges, title, modals, and footer
        $proUser = User::factory()->create([
            'email' => 'pro_vip@tunneldeck.com',
            'package' => 'pro',
            'status' => 'active',
        ]);

        $bundle = $this->createSignedBundle([
            'email' => 'pro_vip@tunneldeck.com',
            'package' => 'pro',
            'tier' => 'Pro',
            'status' => 'active',
            'license_key' => 'TD-PRO-FRONTEND-TEST',
            'hwid' => app(\App\Services\HardwareFingerprintService::class)->getHwid(),
            'issued_at' => time(),
        ]);
        $proUser->update(['signed_license' => $bundle]);

        $this->assertTrue($proUser->isPro());

        $proResponse = $this->actingAs($proUser)->get(route('dashboard'));
        $proResponse->assertStatus(200);
        // Logo brand edition badge switches to Pro with amber styling
        $proResponse->assertSee('<span id="brand-edition-badge"', false);
        $proResponse->assertSee('>Pro</span>', false);
        $proResponse->assertSee('bg-amber-100 text-amber-800 border border-amber-300', false);
        // Browser tab title switches to Pro
        $proResponse->assertSee('Dashboard — TunnelDeck Pro', false);
        // Modals and footer notes switch to Pro
        $proResponse->assertSee('app-edition-label" data-app-edition>Pro</span>', false);
    }

    public function test_login_page_renders_pro_edition_when_pro_user_exists(): void
    {
        // Initially no Pro user -> Login shows Lite
        User::query()->delete();
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Sign In — TunnelDeck Lite', false);

        // When a Pro user exists -> Login page badge and title dynamically show Pro
        User::factory()->create([
            'email' => 'pro_admin@tunneldeck.com',
            'package' => 'pro',
        ]);

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Sign In — TunnelDeck Pro', false);
        $response->assertSee('>Pro</span>', false);
        $response->assertSee('bg-amber-100 text-amber-800 border-amber-300', false);
    }
}
