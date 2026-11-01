<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_payment_routes(): void
    {
        $this->getJson('/api/payment/config')->assertStatus(401);
        $this->postJson('/api/payment/pro-success', [])->assertStatus(401);
        $this->postJson('/api/payment/coffee-success', [])->assertStatus(401);
    }

    public function test_payment_config_returns_razorpay_details_and_pro_plan(): void
    {
        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/payment/config' => Http::response([
                'success' => true,
                'gateway' => 'razorpay',
                'key_id' => 'rzp_test_TuXla2n6Mm2F27',
                'currency' => 'INR',
                'pro_plan' => [
                    'price' => 1499,
                    'currency' => 'INR',
                    'features' => [
                        'Unlimited tunnels',
                        'Unlimited custom domains',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['package' => 'lite']);

        $response = $this->actingAs($user)->getJson('/api/payment/config');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('gateway', 'razorpay')
            ->assertJsonPath('key_id', 'rzp_test_TuXla2n6Mm2F27')
            ->assertJsonPath('pro_plan.price', 1499);
    }

    public function test_pro_success_upgrades_user_package_and_logs_activity(): void
    {
        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/payment/success' => Http::response([
                'success' => true,
                'message' => 'Payment recorded and package upgraded to pro.',
                'package' => 'pro',
            ], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'package' => 'lite',
            'currency' => 'INR',
        ]);

        $payload = [
            'payment_id' => 'pay_test_123456789',
            'order_id' => 'order_987654321',
            'amount' => 1499,
            'currency' => 'INR',
        ];

        $response = $this->actingAs($user)->postJson('/api/payment/pro-success', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('package', 'pro')
            ->assertJsonPath('user.package', 'pro');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'package' => 'pro',
        ]);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'type' => 'success',
        ]);
    }

    public function test_pro_success_validates_input(): void
    {
        $user = User::factory()->create(['package' => 'lite']);

        $response = $this->actingAs($user)->postJson('/api/payment/pro-success', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_id', 'amount']);
    }

    public function test_coffee_success_records_tip_and_leaves_package_unchanged(): void
    {
        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/payment/success' => Http::response([
                'success' => true,
                'message' => 'Coffee tip recorded successfully.',
                'payment_type' => 'coffee',
            ], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'Dev Supporter',
            'email' => 'dev@example.com',
            'package' => 'lite',
            'currency' => 'INR',
        ]);

        $payload = [
            'payment_id' => 'pay_coffee_999888',
            'order_id' => null,
            'amount' => 250,
            'currency' => 'INR',
            'message' => 'Great work on TunnelDeck Lite!',
        ];

        $response = $this->actingAs($user)->postJson('/api/payment/coffee-success', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('amount', 250)
            ->assertJsonPath('currency', 'INR');

        // User package should still be lite but coffee_donated_at is updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'package' => 'lite',
        ]);

        $this->assertNotNull($user->fresh()->coffee_donated_at);
        $this->assertFalse($user->fresh()->shouldShowCoffeeCard());

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'type' => 'info',
        ]);
    }

    public function test_coffee_card_visibility_conditions(): void
    {
        // 1. Lite user with no donations should see coffee card
        $userLite = User::factory()->create(['package' => 'lite', 'coffee_donated_at' => null]);
        $this->assertTrue($userLite->shouldShowCoffeeCard());

        // 2. Lite user who donated 10 days ago should NOT see coffee card (under 30 days)
        $userDonatedRecently = User::factory()->create([
            'package' => 'lite',
            'coffee_donated_at' => now()->subDays(10),
        ]);
        $this->assertFalse($userDonatedRecently->shouldShowCoffeeCard());

        // 3. Lite user who donated 31 days ago SHOULD see coffee card (30 days passed)
        $userDonatedLongAgo = User::factory()->create([
            'package' => 'lite',
            'coffee_donated_at' => now()->subDays(31),
        ]);
        $this->assertTrue($userDonatedLongAgo->shouldShowCoffeeCard());

        // 4. Pro user should NEVER see coffee card
        $userPro = User::factory()->create(['package' => 'pro', 'coffee_donated_at' => null]);
        $this->assertFalse($userPro->shouldShowCoffeeCard());

        // 5. Enterprise user should NEVER see coffee card
        $userEnterprise = User::factory()->create(['package' => 'enterprise', 'coffee_donated_at' => null]);
        $this->assertFalse($userEnterprise->shouldShowCoffeeCard());
    }

    public function test_coffee_card_message_switches_for_returning_donors(): void
    {
        // 1. First-time user sees standard friendly prompt
        $userFirstTime = User::factory()->create(['package' => 'lite', 'coffee_donated_at' => null]);
        $this->assertEquals(
            'Enjoying TunnelDeck Lite? Support development with a small coffee!',
            $userFirstTime->getCoffeeCardMessage()
        );

        // 2. Returning donor whose 30-day period has elapsed (next month) sees personalized thank you prompt
        $userReturning = User::factory()->create([
            'package' => 'lite',
            'coffee_donated_at' => now()->subDays(31),
        ]);
        $this->assertTrue($userReturning->shouldShowCoffeeCard());
        $this->assertEquals(
            'Last month I really enjoyed my coffee that you have paid for. Share another coffee that will help me concentrate on further development',
            $userReturning->getCoffeeCardMessage()
        );
    }

    public function test_coffee_success_validates_input(): void
    {
        $user = User::factory()->create(['package' => 'lite']);

        $response = $this->actingAs($user)->postJson('/api/payment/coffee-success', [
            'amount' => 0, // below min:1
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_id', 'amount']);
    }

    public function test_pro_success_downloads_and_merges_pro_files_into_src(): void
    {
        $tempZipPath = storage_path('app/test_pro_upgrade.zip');
        $zip = new \ZipArchive();
        $zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('src/app/Models/TestProFeature.php', '<?php namespace App\Models; class TestProFeature {}');
        $zip->close();
        $zipContent = file_get_contents($tempZipPath);
        @unlink($tempZipPath);

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/payment/success' => Http::response([
                'success' => true,
                'package' => 'pro',
            ], 200),
            'https://licensing.tunneldeck.online/api/v1/users/*/pro-package/download' => Http::response(
                $zipContent,
                200,
                ['Content-Type' => 'application/octet-stream']
            ),
        ]);

        $user = User::factory()->create([
            'name' => 'Pro Subscriber',
            'email' => 'pro.sub@example.com',
            'package' => 'lite',
        ]);

        $response = $this->actingAs($user)->postJson('/api/payment/pro-success', [
            'payment_id' => 'pay_upgrade_pro_123',
            'amount' => 1499,
            'currency' => 'INR',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('package', 'pro')
            ->assertJsonPath('addon_installed', true);

        // Verify file was merged into project src
        $targetFile = base_path('app/Models/TestProFeature.php');
        $this->assertFileExists($targetFile);
        @unlink($targetFile);
    }

    public function test_install_pro_package_endpoint_downloads_and_merges_files(): void
    {
        $tempZipPath = storage_path('app/test_pro_install.zip');
        $zip = new \ZipArchive();
        $zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('src/app/Models/TestInstallModel.php', '<?php namespace App\Models; class TestInstallModel {}');
        $zip->close();
        $zipContent = file_get_contents($tempZipPath);
        @unlink($tempZipPath);

        Http::fake([
            'https://licensing.tunneldeck.online/api/v1/users/*/pro-package/download' => Http::response(
                $zipContent,
                200,
                ['Content-Type' => 'application/octet-stream']
            ),
        ]);

        $user = User::factory()->create([
            'name' => 'Pro Direct User',
            'email' => 'pro.direct@example.com',
            'package' => 'lite',
        ]);

        $response = $this->actingAs($user)->postJson('/api/license/install-pro-package');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('package', 'pro');

        $targetFile = base_path('app/Models/TestInstallModel.php');
        $this->assertFileExists($targetFile);
        @unlink($targetFile);
    }
}

