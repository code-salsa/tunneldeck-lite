<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_user_model_max_tunnels_by_package(): void
    {
        $user = User::factory()->create(['package' => 'lite']);
        $this->assertEquals(10, $user->maxTunnels());

        $user->package = 'pro';
        $this->assertEquals(50, $user->maxTunnels());

        $user->package = 'enterprise';
        $this->assertEquals(500, $user->maxTunnels());

        $user->package = 'unknown';
        $this->assertEquals(10, $user->maxTunnels());
    }

    public function test_license_sync_endpoint_updates_user(): void
    {
        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*' => Http::response([
                'success' => true,
                'data' => [
                    'package' => 'pro',
                    'currency' => 'INR',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'email' => 'client@test.com',
            'package' => 'lite',
        ]);

        $response = $this->actingAs($user)->getJson('/api/license/sync');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'package' => 'pro',
            'currency' => 'INR',
        ]);

        $this->assertEquals('pro', $user->fresh()->package);
        $this->assertNotNull($user->fresh()->license_synced_at);
    }

    public function test_license_upgrade_test_endpoint(): void
    {
        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*/upgrades' => Http::response([
                'success' => true,
                'data' => [
                    'to_package' => 'enterprise',
                    'amount' => 1499.00,
                ],
            ], 200),
            'https://licensing.tunneldeck.online/api/v1/users/*' => Http::response([
                'success' => true,
                'data' => [
                    'package' => 'enterprise',
                    'currency' => 'INR',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'email' => 'client@test.com',
            'package' => 'pro',
        ]);

        $response = $this->actingAs($user)->postJson('/api/license/upgrade-test', [
            'to_package' => 'enterprise',
            'amount' => 1499.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'package' => 'enterprise',
        ]);

        $this->assertEquals('enterprise', $user->fresh()->package);
    }

    public function test_license_run_test_flow(): void
    {
        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/register' => Http::response([
                'success' => true,
                'data' => ['id' => 10],
            ], 200),
            'https://licensing.tunneldeck.online/api/v1/users/*/access-logs' => Http::response([
                'success' => true,
            ], 200),
            'https://licensing.tunneldeck.online/api/v1/users/*/activity-logs' => Http::response([
                'success' => true,
            ], 200),
            'https://licensing.tunneldeck.online/api/v1/users/*/upgrades' => Http::response([
                'success' => true,
            ], 200),
            'https://licensing.tunneldeck.online/api/v1/users/*' => Http::response([
                'success' => true,
                'data' => [
                    'package' => 'pro',
                    'currency' => 'INR',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'email' => 'client@test.com',
            'package' => 'lite',
        ]);

        $response = $this->actingAs($user)->postJson('/api/license/test-flow');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'email',
            'steps' => [
                '*' => ['name', 'endpoint', 'status', 'success', 'duration_ms'],
            ],
            'all_passed',
        ]);

        $this->assertTrue($response->json('all_passed'));
        $this->assertCount(5, $response->json('steps'));
    }

    public function test_incoming_webhook_license_update(): void
    {
        $user = User::factory()->create([
            'email' => 'client@test.com',
            'package' => 'lite',
        ]);

        $response = $this->actingAs($user)->postJson('/api/webhook/license-update', [
            'email' => 'client@test.com',
            'package' => 'enterprise',
            'currency' => 'USD',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'package' => 'enterprise',
        ]);

        $this->assertEquals('enterprise', $user->fresh()->package);
        $this->assertEquals('USD', $user->fresh()->currency);
    }
}

