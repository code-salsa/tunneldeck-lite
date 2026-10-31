<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Unonboarded user sees the onboarding modal on the dashboard.
     */
    public function test_unonboarded_user_sees_onboarding_modal(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();
        $this->assertFalse((bool) $user->is_onboarded);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);
        $response->assertSee('id="onboarding-modal"', false);
        $response->assertSee('Let\'s set up your profile', false);
    }

    /**
     * User can complete full multi-step onboarding and replace default superadmin credentials.
     */
    public function test_user_can_complete_onboarding_and_replace_superadmin_credentials(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/*' => Http::response([
                'success' => true,
                'message' => 'User registered successfully.',
            ], 200),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user)->postJson('/onboarding/complete', [
            'name' => 'Aarav Sharma',
            'email' => 'aarav.sharma@example.in',
            'city' => 'Bengaluru',
            'country' => 'India',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'name' => 'Aarav Sharma',
                    'email' => 'aarav.sharma@example.in',
                    'initial' => 'A',
                ],
            ]);

        // Verify local DB credentials replaced
        $updatedUser = User::find($user->id);
        $this->assertEquals('Aarav Sharma', $updatedUser->name);
        $this->assertEquals('aarav.sharma@example.in', $updatedUser->email);
        $this->assertEquals('Bengaluru', $updatedUser->city);
        $this->assertEquals('India', $updatedUser->country);
        $this->assertTrue((bool) $updatedUser->is_onboarded);
        $this->assertTrue(Hash::check('NewSecurePassword123!', $updatedUser->password));

        // Verify onboarding modal is gone on next dashboard visit
        $dashResponse = $this->actingAs($updatedUser)->get('/');
        $dashResponse->assertStatus(200);
        $dashResponse->assertDontSee('id="onboarding-modal"', false);
    }

    /**
     * User can explore platform with email only.
     */
    public function test_user_can_explore_platform_with_email_only(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/*' => Http::response([
                'success' => true,
                'message' => 'Exploration access recorded.',
            ], 200),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user)->postJson('/onboarding/explore', [
            'email' => 'explorer.dev@example.com',
            'name' => 'Quick Explorer',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $updatedUser = User::find($user->id);
        $this->assertEquals('explorer.dev@example.com', $updatedUser->email);
        $this->assertTrue((bool) $updatedUser->is_onboarded);
    }

    /**
     * Exploring requires an email address.
     */
    public function test_explore_platform_requires_email(): void
    {
        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user)->postJson('/onboarding/explore', [
            'email' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
