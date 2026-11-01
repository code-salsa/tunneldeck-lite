<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_default_admin_can_login_without_otp(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@tunneldeck.online',
            'password' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'token',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJson([
                'status' => 'success',
                'user' => [
                    'email' => 'admin@tunneldeck.online',
                ],
            ]);
    }

    public function test_invalid_credentials_fail(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@tunneldeck.online',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_default_admin_can_login_via_web(): void
    {
        $response = $this->postJson('/login', [
            'email' => 'admin@tunneldeck.online',
            'password' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Login successful',
            ]);

        $this->assertAuthenticated();
    }

    public function test_default_admin_fallback_when_account_renamed_in_onboarding(): void
    {
        // Simulate user renamed during onboarding
        $user = User::first();
        $user->update(['email' => 'custom_owner@domain.com']);

        // Attempt login using the default credentials
        $response = $this->postJson('/login', [
            'email' => 'admin@tunneldeck.online',
            'password' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Login successful',
            ]);

        $this->assertAuthenticated();
    }
}
