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
            'subdomain' => 'docs-site',
            'protocol' => 'http',
            'port' => 3001,
            'target_host' => '127.0.0.1',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'tunnel' => [
                    'name' => 'Docs Server',
                    'subdomain' => 'docs-site',
                    'port' => 3001,
                ],
            ]);

        $this->assertDatabaseHas('tunnels', [
            'subdomain' => 'docs-site',
        ]);
    }

    public function test_unauthenticated_request_is_blocked(): void
    {
        $response = $this->getJson('/api/tunnels');
        $response->assertStatus(401);
    }
}
