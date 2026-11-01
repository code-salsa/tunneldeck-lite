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

        // Verify licensing complete-onboarding endpoint was invoked
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/complete-onboarding');
        });
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
        $this->assertEquals('Quick Explorer', $updatedUser->name);
        $this->assertFalse((bool) $updatedUser->is_onboarded);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/users/register')
                && $request['email'] === 'explorer.dev@example.com'
                && $request['name'] === 'Quick Explorer';
        });

        // Next time user opens application / dashboard, show onboarding modal again with pre-filled fields
        $dashResponse = $this->actingAs($updatedUser)->get('/');
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('id="onboarding-modal"', false);
        $dashResponse->assertDontSee('id="onboarding-modal" class="hidden', false);
        $dashResponse->assertSee('value="explorer.dev@example.com"', false);
        $dashResponse->assertSee('value="Quick Explorer"', false);
        $dashResponse->assertSee('id="continue-onboarding-bar"', false);
        $dashResponse->assertSee('Continue Onboarding', false);
    }

    /**
     * Uncompleted onboarding shows modal again on next app open with prefilled fields.
     */
    public function test_uncompleted_onboarding_prefills_previously_filled_fields_on_next_open(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/*' => Http::response(['success' => true], 200),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        // 1. User enters profile details and clicks explore
        $this->actingAs($user)->postJson('/onboarding/explore', [
            'name' => 'Tusar Developer',
            'email' => 'tusar@example.com',
            'city' => 'Bengaluru',
            'country' => 'India',
        ]);

        // 2. Next time user opens the application (fresh request / new session)
        $reopenedUser = User::find($user->id);
        $this->assertFalse((bool) $reopenedUser->is_onboarded);

        $response = $this->actingAs($reopenedUser)->get('/');
        $response->assertStatus(200);

        // Onboarding screen is shown again
        $response->assertSee('id="onboarding-modal"', false);
        $response->assertDontSee('id="onboarding-modal" class="hidden', false);

        // All fields from previous exploration are pre-filled
        $response->assertSee('value="Tusar Developer"', false);
        $response->assertSee('value="tusar@example.com"', false);
        $response->assertSee('value="Bengaluru"', false);
        $response->assertSee('value="India" selected', false);
    }

    /**
     * Completing onboarding removes the exploration bar and completes setup.
     */
    public function test_continue_onboarding_bar_dismissed_after_full_onboarding(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/*' => Http::response([
                'success' => true,
                'message' => 'Synced',
            ], 200),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        // 1. Explore first
        $this->actingAs($user)->postJson('/onboarding/explore', [
            'email' => 'explorer.first@example.com',
            'name' => 'First Explorer',
        ]);

        // Dashboard has continue bar
        $dash1 = $this->actingAs($user)->get('/');
        $dash1->assertSee('id="continue-onboarding-bar"', false);

        // 2. Complete full onboarding
        $completeRes = $this->actingAs($user)->postJson('/onboarding/complete', [
            'name' => 'Final Name',
            'email' => 'final@example.com',
            'city' => 'Mumbai',
            'country' => 'India',
            'password' => 'SecurePass987!',
            'password_confirmation' => 'SecurePass987!',
        ]);
        $completeRes->assertStatus(200);

        // 3. Dashboard now hides the bar and modal, and tunnels list is completely blank
        $updatedUser = User::find($user->id);
        $this->assertTrue((bool) $updatedUser->is_onboarded);
        $this->assertEquals(0, \App\Models\Tunnel::count(), 'Tunnels list must be completely blank after onboarding completes');

        $dash2 = $this->actingAs($updatedUser)->get('/');
        $dash2->assertDontSee('id="onboarding-modal"', false);
    }

    /**
     * Dummy tunnels are populated during exploration, can be started via simulation, and are wiped on onboarding completion.
     */
    public function test_dummy_tunnels_seeded_on_explore_and_simulated_start_and_cleaned_on_complete(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/*' => Http::response(['success' => true], 200),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        // 1. Enter exploration mode
        $this->actingAs($user)->postJson('/onboarding/explore', [
            'email' => 'preview.dev@example.com',
            'name' => 'Preview User',
        ]);

        // Verify dummy tunnels exist and are marked is_dummy
        $dummyTunnels = \App\Models\Tunnel::where('is_dummy', true)->get();
        $this->assertCount(3, $dummyTunnels);

        $firstDummy = $dummyTunnels->first();
        $this->assertEquals('stopped', $firstDummy->status);

        // 2. Start a dummy tunnel (simulation mode without launching OS process)
        $startRes = $this->actingAs($user)->postJson("/api/tunnels/{$firstDummy->id}/start", [
            'routing_strategy' => 'direct',
        ]);

        $startRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'is_dummy' => true,
                'demo_remaining_seconds' => 60,
            ])
            ->assertJsonStructure([
                'simulation_steps' => [
                    '*' => ['step', 'title', 'status', 'desc']
                ]
            ]);

        $this->assertEquals('running', $firstDummy->fresh()->status);

        // Verify calling /api/stats or /api/tunnels does NOT stop the tunnel in 10ms
        $statsRes = $this->actingAs($user)->getJson('/api/stats');
        $statsRes->assertStatus(200);
        $this->assertEquals('running', $firstDummy->fresh()->status, 'Dummy tunnel must remain running and not immediately stop');

        $tunnelsRes = $this->actingAs($user)->getJson('/api/tunnels');
        $tunnelsRes->assertStatus(200);
        $this->assertEquals('running', $firstDummy->fresh()->status);

        // Verify auto-stop after 60 seconds
        $firstDummy->update(['last_started_at' => now()->subSeconds(65)]);
        $expiredRes = $this->actingAs($user)->getJson('/api/tunnels');
        $expiredRes->assertStatus(200);
        $this->assertEquals('stopped', $firstDummy->fresh()->status, 'Dummy tunnel must auto-stop after 60 seconds');

        // 3. Complete onboarding -> All tunnels are wiped blank
        $completeRes = $this->actingAs($user)->postJson('/onboarding/complete', [
            'name' => 'Real Developer',
            'email' => 'real.dev@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
        $completeRes->assertStatus(200);

        // Tunnels list is now completely blank
        $this->assertEquals(0, \App\Models\Tunnel::count());
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

    /**
     * Platform settings cannot be changed in simulation/demo mode, and the banner/messages are displayed.
     */
    public function test_platform_settings_cannot_be_changed_in_simulation_mode_and_message_shown(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/*' => Http::response(['success' => true], 200),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        // 1. Enter exploration mode
        $this->actingAs($user)->postJson('/onboarding/explore', [
            'email' => 'demo.user@example.com',
            'name' => 'Demo User',
        ]);

        $this->assertTrue($user->fresh()->isExploring());

        // 2. View dashboard -> Settings tab shows demo banner, locked notices, and disabled controls
        $dashRes = $this->actingAs($user)->get('/');
        $dashRes->assertStatus(200);
        $dashRes->assertSee('id="settings-demo-banner"', false);
        $dashRes->assertSee('Platform settings cannot be changed in simulation mode.', false);
        $dashRes->assertSee('id="settings-password-locked-notice"', false);
        $dashRes->assertSee('Locked in Simulation Mode', false);

        // 3. Attempting to change password in simulation mode is blocked with 403
        $pwRes = $this->actingAs($user)->postJson('/user/password', [
            'current_password' => '123456',
            'new_password' => 'NewSecurePass999!',
        ]);
        $pwRes->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Platform settings cannot be changed in simulation mode. Please complete onboarding first.',
            ]);

        // 4. Attempting to run test plan upgrade in simulation mode is blocked with 403
        $upgradeRes = $this->actingAs($user)->postJson('/api/license/upgrade-test', [
            'to_package' => 'pro',
        ]);
        $upgradeRes->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Platform settings cannot be changed in simulation mode. Please complete onboarding first.',
            ]);

        // 5. Complete onboarding -> Settings are unlocked
        $this->actingAs($user)->postJson('/onboarding/complete', [
            'name' => 'Real Developer',
            'email' => 'real.dev@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $this->assertFalse($user->fresh()->isExploring());
        $this->assertTrue((bool) $user->fresh()->is_onboarded);

        // Password change is now allowed
        $pwResAfter = $this->actingAs($user->fresh())->postJson('/user/password', [
            'current_password' => 'SecurePass123!',
            'new_password' => 'BrandNewPassword123!',
        ]);
        $pwResAfter->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Password updated successfully.',
            ]);
    }

    /**
     * User can dispatch 4-digit verification code before completing onboarding.
     */
    public function test_user_can_dispatch_registration_otp(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/api/v1/auth/send-otp' => Http::response([
                'success' => true,
                'message' => 'A 4-digit verification code has been dispatched to test@example.com.',
                'email' => 'test@example.com',
                'otp_expires_in' => 15,
            ], 200),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user)->postJson('/onboarding/send-otp', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'email' => 'test@example.com',
            ]);
    }

    /**
     * User cannot complete onboarding when invalid 4-digit OTP is provided.
     */
    public function test_user_cannot_complete_onboarding_with_invalid_otp(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/api/v1/auth/verify-otp' => Http::response([
                'success' => false,
                'message' => 'Invalid or expired 4-digit verification code.',
            ], 422),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user)->postJson('/onboarding/complete', [
            'name' => 'Aarav Sharma',
            'email' => 'aarav@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'otp' => '9999',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertFalse((bool) $user->fresh()->is_onboarded);
    }

    /**
     * User can complete onboarding when valid 4-digit OTP is provided.
     */
    public function test_user_can_complete_onboarding_with_valid_otp(): void
    {
        Http::fake([
            'licensing.tunneldeck.online/api/v1/auth/verify-otp' => Http::response([
                'success' => true,
                'message' => 'Email verified successfully!',
            ], 200),
            'licensing.tunneldeck.online/*' => Http::response([
                'success' => true,
                'message' => 'Success',
            ], 200),
        ]);

        $user = User::where('email', 'admin@tunneldeck.online')->first();

        $response = $this->actingAs($user)->postJson('/onboarding/complete', [
            'name' => 'Aarav Sharma',
            'email' => 'aarav.verified@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'otp' => '1234',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue((bool) $user->fresh()->is_onboarded);
        $this->assertEquals('aarav.verified@example.com', $user->fresh()->email);
    }
}

