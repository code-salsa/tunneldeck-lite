<?php

namespace Database\Seeders;

use App\Models\Activity;
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
        $user = User::updateOrCreate(
            ['email' => 'admin@tunneldeck.online'],
            [
                'name' => 'TunnelDeck Admin',
                'password' => Hash::make('123456'),
                'email_verified_at' => now(),
            ]
        );

        // Sample Tunnels
        Tunnel::updateOrCreate(
            ['port' => 8000],
            [
                'user_id' => $user->id,
                'name' => 'Backend API Tunnel',
                'status' => 'stopped',
                'custom_subdomain' => 'api-dev',
            ]
        );

        Tunnel::updateOrCreate(
            ['port' => 3001],
            [
                'user_id' => $user->id,
                'name' => 'NextJS Frontend Preview',
                'status' => 'stopped',
                'custom_subdomain' => 'frontend-app',
            ]
        );

        Activity::create([
            'user_id' => $user->id,
            'description' => 'TunnelDeck Lite Initialized with Cloudflare Tunneling Engine',
            'type' => 'info',
        ]);
    }
}
