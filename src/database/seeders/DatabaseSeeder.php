<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\CustomDomain;
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
                'package' => 'lite',
                'is_onboarded' => false,
                'city' => null,
                'country' => null,
                'currency' => 'USD',
                'license_synced_at' => null,
                'coffee_donated_at' => null,
            ]
        );

        // Sample Simulation Tunnels
        \App\Http\Controllers\OnboardingController::seedDummyTunnels($user->id);

        Activity::create([
            'user_id' => $user->id,
            'description' => 'TunnelDeck Lite Initialized with Cloudflare Tunneling Engine',
            'type' => 'info',
        ]);
    }
}
