<?php

namespace Tests\Feature;

use App\Models\Tunnel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        \App\Services\TunnelHealthService::$forcePortRunning = null;
        parent::tearDown();
    }

    public function test_authenticated_user_can_list_tunnels(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tunnels');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'tunnels',
                    'metrics' => ['total', 'active', 'paused', 'total_requests'],
                ],
            ]);
    }

    public function test_authenticated_user_can_create_tunnel(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tunnels', [
            'name' => 'Docs Server',
            'custom_subdomain' => 'docs-site',
            'port' => 4500,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'tunnel' => [
                    'name' => 'Docs Server',
                    'port' => 4500,
                    'custom_subdomain' => 'docs-site',
                ],
            ]);

        $this->assertDatabaseHas('tunnels', [
            'name' => 'Docs Server',
            'port' => 4500,
        ]);
    }

    public function test_authenticated_user_can_update_tunnel(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $tunnel = Tunnel::first();

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/tunnels/{$tunnel->id}", [
            'name' => 'Renamed Dev Server',
            'port' => 8080,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'tunnel' => [
                    'id' => $tunnel->id,
                    'name' => 'Renamed Dev Server',
                    'port' => 8080,
                ],
            ]);

        $this->assertDatabaseHas('tunnels', [
            'id' => $tunnel->id,
            'name' => 'Renamed Dev Server',
            'port' => 8080,
        ]);
    }

    public function test_authenticated_user_can_delete_tunnel(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $tunnel = Tunnel::first();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/tunnels/{$tunnel->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseMissing('tunnels', [
            'id' => $tunnel->id,
        ]);
    }

    public function test_tunnel_name_uniqueness_validation_returns_name_taken_error(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $existing = Tunnel::first();

        // Attempt to create another tunnel with the exact same name
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tunnels', [
            'name' => $existing->name,
            'port' => 9999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonPath('errors.name.0', 'The name has already been taken.');
    }

    public function test_tunnel_limit_endpoint_returns_limit_info(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $user->update(['package' => 'lite']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tunnels/limit');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'max_tunnels',
                    'is_unlimited',
                    'total_created',
                    'remaining',
                    'limit_reached',
                    'package',
                ],
            ]);
    }

    public function test_tunnel_creation_enforces_limit_for_lite_user(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $user->update(['package' => 'lite']);

        // Lite limit is 5 tunnels. Seed existing tunnels up to 5.
        $existingCount = Tunnel::count();
        for ($i = $existingCount; $i < 5; $i++) {
            Tunnel::create([
                'user_id' => $user->id,
                'name' => "Limit Filler Tunnel {$i}",
                'port' => 6000 + $i,
                'status' => 'stopped',
            ]);
        }

        // Attempting to create the 6th tunnel should be blocked with 403
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tunnels', [
            'name' => 'Excess Tunnel 6',
            'port' => 7777,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error_code', 'TUNNEL_LIMIT_REACHED')
            ->assertJsonPath('message', 'You have reached the number of allowed tunnel creation. Upgrade to a pro plan to enjoy unlimited tunnels.');
    }

    public function test_pro_user_can_create_unlimited_tunnels(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $user->update(['package' => 'pro']);

        // Fill up 5 tunnels
        $existingCount = Tunnel::count();
        for ($i = $existingCount; $i < 5; $i++) {
            Tunnel::create([
                'user_id' => $user->id,
                'name' => "Pro Filler Tunnel {$i}",
                'port' => 8000 + $i,
                'status' => 'stopped',
            ]);
        }

        // Pro user can create 6th tunnel without 403
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tunnels', [
            'name' => 'Pro Unlimited Tunnel 6',
            'port' => 8888,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    public function test_authenticated_user_can_bulk_delete_multiple_tunnels(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $t1 = Tunnel::create([
            'user_id' => $user->id,
            'name' => 'Bulk Tunnel A',
            'port' => 9101,
            'status' => 'running',
            'pid' => 99991,
        ]);

        $t2 = Tunnel::create([
            'user_id' => $user->id,
            'name' => 'Bulk Tunnel B',
            'port' => 9102,
            'status' => 'stopped',
        ]);

        $t3 = Tunnel::create([
            'user_id' => $user->id,
            'name' => 'Bulk Tunnel Keep',
            'port' => 9103,
            'status' => 'stopped',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tunnels/bulk-delete', [
            'ids' => [$t1->id, $t2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'deleted_count' => 2,
            ]);

        $this->assertDatabaseMissing('tunnels', ['id' => $t1->id]);
        $this->assertDatabaseMissing('tunnels', ['id' => $t2->id]);
        $this->assertDatabaseHas('tunnels', ['id' => $t3->id]);

        // Also test DELETE HTTP method
        $t4 = Tunnel::create([
            'user_id' => $user->id,
            'name' => 'Bulk Tunnel Delete Method',
            'port' => 9104,
            'status' => 'stopped',
        ]);

        $responseDelete = $this->actingAs($user, 'sanctum')->deleteJson('/api/tunnels/bulk-delete', [
            'ids' => [$t4->id],
        ]);

        $responseDelete->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'deleted_count' => 1,
            ]);

        $this->assertDatabaseMissing('tunnels', ['id' => $t4->id]);
    }

    public function test_bulk_delete_requires_valid_ids(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tunnels/bulk-delete', [
            'ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_unauthenticated_request_is_blocked(): void
    {
        $response = $this->getJson('/api/tunnels');
        $response->assertStatus(401);
    }

    public function test_golden_rule_tunnel_name_uniqueness_case_insensitive(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $existing = Tunnel::first();

        // Attempt to create another tunnel with lowercase/uppercase variant of existing name
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tunnels', [
            'name' => strtolower($existing->name),
            'port' => 7777,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonPath('error_code', 'TUNNEL_NAME_TAKEN')
            ->assertJsonPath('field', 'name');
    }

    public function test_golden_rule_port_must_be_in_running_condition_or_fails(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();

        // Explicitly set port not in running condition
        \App\Services\TunnelHealthService::$forcePortRunning = false;

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tunnels', [
            'name' => 'Dead Port Service',
            'port' => 59999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['port'])
            ->assertJsonPath('error_code', 'PORT_NOT_RUNNING')
            ->assertJsonPath('field', 'port')
            ->assertJsonPath('port', 59999);

        $this->assertStringContainsString('running condition', $response->json('message'));
        $this->assertDatabaseMissing('tunnels', [
            'name' => 'Dead Port Service',
        ]);
    }

    public function test_check_port_endpoint_verifies_running_condition(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();

        // 1. When port is running
        \App\Services\TunnelHealthService::$forcePortRunning = true;
        $respRunning = $this->actingAs($user)->getJson('/api/tunnels/check-port/3000');
        $respRunning->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('running', true)
            ->assertJsonPath('port', 3000);

        // 2. When port is not running
        \App\Services\TunnelHealthService::$forcePortRunning = false;
        $respDead = $this->actingAs($user)->getJson('/api/tunnels/check-port/3000');
        $respDead->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('running', false)
            ->assertJsonPath('port', 3000);
    }
}
