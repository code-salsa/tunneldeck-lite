<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Tunnel;
use App\Models\User;
use App\Services\TunnelDeckService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientDeletionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Test that when licensing server returns 404 or client_deleted,
     * syncUserLicense automatically resets the local SQLite database to factory defaults.
     */
    public function test_sync_user_license_resets_database_when_client_deleted_on_licensing_platform(): void
    {
        // 1. Setup an active onboarded user with custom tunnels and activities
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $user->name = 'Jane Developer';
        $user->email = 'jane@example.com';
        $user->is_onboarded = true;
        $user->package = 'lite';
        $user->save();

        Tunnel::create([
            'user_id' => $user->id,
            'name' => 'Custom User Tunnel',
            'port' => 4500,
            'status' => 'stopped',
        ]);

        Activity::create([
            'user_id' => $user->id,
            'description' => 'Jane created a custom webhook tunnel',
            'type' => 'info',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseHas('tunnels', ['port' => 4500]);

        // 2. Mock licensing server returning 404 client_deleted
        Http::fake([
            'licensing.tunneldeck.online/api/v1/users/jane@example.com' => Http::response([
                'success' => false,
                'client_deleted' => true,
                'message' => 'Client has been deleted from licensing platform.',
            ], 404),
        ]);

        // 3. User calls sync API endpoint
        $response = $this->actingAs($user)->getJson('/api/license/sync');

        $response->assertStatus(200);
        $response->assertJson([
            'client_deleted' => true,
            'reset_to_defaults' => true,
            'redirect' => '/login',
        ]);

        // 4. Verify local database was completely reset to factory defaults
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseMissing('tunnels', ['port' => 4500]);

        // Default admin restored
        $defaultAdmin = User::where('email', 'admin@tunneldeck.online')->first();
        $this->assertNotNull($defaultAdmin);
        $this->assertEquals('TunnelDeck Admin', $defaultAdmin->name);
        $this->assertFalse((bool) $defaultAdmin->is_onboarded);
        $this->assertEquals('lite', $defaultAdmin->package);
        $this->assertTrue(Hash::check('123456', $defaultAdmin->password));

        // Default sample tunnels restored
        $this->assertDatabaseHas('tunnels', ['port' => 8000, 'name' => 'Sample REST API Backend']);
        $this->assertDatabaseHas('tunnels', ['port' => 3000, 'name' => 'Demo Next.js Frontend']);
        $this->assertDatabaseHas('tunnels', ['port' => 4242, 'name' => 'Mock Payment Webhook']);
    }

    /**
     * Test direct webhook invocation from licensing platform to tunneldeck-lite.
     */
    public function test_client_deleted_webhook_resets_database_to_defaults(): void
    {
        // 1. Setup an active onboarded user
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $user->name = 'Mark Explorer';
        $user->email = 'mark@tunneldeck.test';
        $user->is_onboarded = true;
        $user->save();

        Tunnel::create([
            'user_id' => $user->id,
            'name' => 'Mark Live Microservice',
            'port' => 9090,
            'status' => 'stopped',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'mark@tunneldeck.test']);
        $this->assertDatabaseHas('tunnels', ['port' => 9090]);

        // 2. Incoming webhook from licensing platform
        $response = $this->postJson('/api/license/client-deleted', [
            'email' => 'mark@tunneldeck.test',
            'event' => 'client_deleted',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'client_deleted' => true,
            'reset_to_defaults' => true,
        ]);

        // 3. Confirm database was restored to default state
        $this->assertDatabaseMissing('users', ['email' => 'mark@tunneldeck.test']);
        $this->assertDatabaseMissing('tunnels', ['port' => 9090]);

        $defaultAdmin = User::where('email', 'admin@tunneldeck.online')->first();
        $this->assertNotNull($defaultAdmin);
        $this->assertEquals('TunnelDeck Admin', $defaultAdmin->name);
        $this->assertFalse((bool) $defaultAdmin->is_onboarded);
    }

    /**
     * Test that default unonboarded admin does not get logged out or reset during heartbeat sync.
     */
    public function test_default_admin_in_exploration_mode_does_not_get_logged_out_by_sync(): void
    {
        $defaultAdmin = User::where('email', 'admin@tunneldeck.online')->first();
        $this->assertFalse((bool) $defaultAdmin->is_onboarded);

        // When licensing heartbeat sync runs for unonboarded default admin
        $response = $this->actingAs($defaultAdmin)->getJson('/api/license/sync');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_onboarded' => false,
        ]);
        $response->assertJsonMissing(['redirect' => '/login']);
        $response->assertJsonMissing(['reset_to_defaults' => true]);

        // Default admin remains intact and logged in
        $this->assertDatabaseHas('users', ['email' => 'admin@tunneldeck.online']);
    }

    /**
     * Test artisan command tunneldeck:reset-defaults.
     */
    public function test_artisan_command_resets_database(): void
    {
        $this->artisan('tunneldeck:reset-defaults')
            ->expectsOutputToContain('Resetting TunnelDeck Lite database to factory defaults...')
            ->assertSuccessful();

        $defaultAdmin = User::where('email', 'admin@tunneldeck.online')->first();
        $this->assertNotNull($defaultAdmin);
        $this->assertEquals('TunnelDeck Admin', $defaultAdmin->name);
    }
}

