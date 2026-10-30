<?php

namespace Database\Seeders;

use App\Models\Tunnel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default Admin User
        User::updateOrCreate(
            ['email' => 'admin@tunneldeck.online'],
            [
                'name' => 'TunnelDeck Admin',
                'password' => Hash::make('123456'),
                'email_verified_at' => now(),
            ]
        );

        // Default Sample Tunnels
        Tunnel::updateOrCreate(
            ['subdomain' => 'api-dev'],
            [
                'name' => 'Backend API Tunnel',
                'protocol' => 'http',
                'port' => 8000,
                'target_host' => '127.0.0.1',
                'public_url' => 'https://api-dev.tunneldeck.online',
                'status' => 'active',
                'connections_count' => 142,
                'last_active_at' => now(),
            ]
        );

        Tunnel::updateOrCreate(
            ['subdomain' => 'frontend-app'],
            [
                'name' => 'NextJS Frontend Preview',
                'protocol' => 'http',
                'port' => 3000,
                'target_host' => 'localhost',
                'public_url' => 'https://frontend-app.tunneldeck.online',
                'status' => 'active',
                'connections_count' => 89,
                'last_active_at' => now()->subMinutes(12),
            ]
        );

        Tunnel::updateOrCreate(
            ['subdomain' => 'webhook-stripe'],
            [
                'name' => 'Stripe Webhook Listener',
                'protocol' => 'http',
                'port' => 4242,
                'target_host' => 'localhost',
                'public_url' => 'https://webhook-stripe.tunneldeck.online',
                'status' => 'paused',
                'connections_count' => 24,
                'last_active_at' => now()->subHours(2),
            ]
        );
    }
}
