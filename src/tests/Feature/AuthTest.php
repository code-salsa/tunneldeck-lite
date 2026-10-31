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
}
