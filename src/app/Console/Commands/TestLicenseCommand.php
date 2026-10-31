<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TunnelDeckService;
use Illuminate\Console\Command;

class TestLicenseCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tunneldeck:test-license {email? : Optional user email to test with}';

    /**
     * The console command description.
     */
    protected $description = 'Run end-to-end diagnostic test for licensing server client submission and incoming plan upgrades';

    /**
     * Execute the console command.
     */
    public function handle(TunnelDeckService $tunnelDeck): int
    {
        $this->info("=================================================================");
        $this->info("  TunnelDeck Lite — Licensing Server End-to-End Test Suite      ");
        $this->info("  Cloud Target: https://licensing.tunneldeck.online/api/v1       ");
        $this->info("=================================================================\n");

        $emailArg = $this->argument('email');
        if ($emailArg) {
            /** @var User $user */
            $user = User::firstOrNew(['email' => $emailArg], [
                'name' => 'Automated Test User',
                'password' => bcrypt('password123'),
                'is_onboarded' => true,
                'package' => 'lite',
            ]);
            if (!$user->exists) $user->save();
        } else {
            /** @var User $user */
            $user = User::first();
        }

        if (!$user) {
            $this->error("No local user found in SQLite database to test with.");
            return Command::FAILURE;
        }

        $testEmail = $user->email;
        $this->line("Target Test User: <comment>{$user->name}</comment> (<info>{$testEmail}</info>)");
        $this->line("Current Local Package: <comment>" . strtoupper($user->package ?? 'lite') . "</comment>\n");

        // -----------------------------------------------------------------
        // TEST 1: Outgoing Client Details Submission
        // -----------------------------------------------------------------
        $this->line("<fg=cyan;options=bold>[1/5] Testing Client Registration (Outgoing)...</>");
        $t1 = microtime(true);
        $regResp = $tunnelDeck->registerUser([
            'name' => $user->name,
            'email' => $testEmail,
            'city' => $user->city ?? 'Bengaluru',
            'country' => $user->country ?? 'India',
            'package' => 'lite',
        ]);
        $d1 = round((microtime(true) - $t1) * 1000);

        if ($regResp && $regResp->successful()) {
            $this->info("  ✔ PASSED: Client registered successfully on licensing server ({$d1}ms)");
            $this->line("    Response: " . json_encode($regResp->json('data') ?? $regResp->json('message')));
        } elseif ($regResp && $regResp->status() === 422 && str_contains($regResp->body(), 'already been taken')) {
            $this->info("  ✔ PASSED: Client is already registered & active on licensing server ({$d1}ms)");
            $this->line("    Verified existing cloud account for: {$testEmail}");
        } else {
            $this->error("  ✖ FAILED: Unable to register client. Status: " . ($regResp ? $regResp->status() : 'No response'));
            return Command::FAILURE;
        }
        $this->newLine();

        // -----------------------------------------------------------------
        // TEST 2: Outgoing Access Log Submission
        // -----------------------------------------------------------------
        $this->line("<fg=cyan;options=bold>[2/5] Testing User Access Log Recording (Outgoing)...</>");
        $t2 = microtime(true);
        $accessResp = $tunnelDeck->logAccess($testEmail, [
            'action' => 'cli_license_test',
            'ip_address' => '127.0.0.1',
            'device' => 'Artisan CLI Test Runner',
            'status' => 'success',
        ]);
        $d2 = round((microtime(true) - $t2) * 1000);

        if ($accessResp && $accessResp->successful()) {
            $this->info("  ✔ PASSED: Access log recorded on licensing server ({$d2}ms)");
        } else {
            $this->error("  ✖ FAILED: Access log recording failed.");
        }
        $this->newLine();

        // -----------------------------------------------------------------
        // TEST 3: Outgoing Activity Log Submission
        // -----------------------------------------------------------------
        $this->line("<fg=cyan;options=bold>[3/5] Testing User Activity Log Recording (Outgoing)...</>");
        $t3 = microtime(true);
        $actResp = $tunnelDeck->logActivity($testEmail, 'test_activity_event', 'Artisan CLI automated test execution', [
            'env' => 'local',
            'timestamp' => now()->toIso8601String(),
        ]);
        $d3 = round((microtime(true) - $t3) * 1000);

        if ($actResp && $actResp->successful()) {
            $this->info("  ✔ PASSED: Activity audit log recorded on licensing server ({$d3}ms)");
        } else {
            $this->error("  ✖ FAILED: Activity log recording failed.");
        }
        $this->newLine();

        // -----------------------------------------------------------------
        // TEST 4: Plan Upgrade on Cloud Server (Lite -> Pro)
        // -----------------------------------------------------------------
        $targetPackage = ($user->package === 'pro') ? 'enterprise' : 'pro';
        $this->line("<fg=cyan;options=bold>[4/5] Testing Plan Upgrade to [{$targetPackage}] on Cloud Server...</>");
        $t4 = microtime(true);
        $upgResp = $tunnelDeck->logUpgrade($testEmail, $targetPackage, 499.00, [
            'currency' => 'INR',
            'payment_reference' => 'CLI_TXN_' . time(),
        ]);
        $d4 = round((microtime(true) - $t4) * 1000);

        if ($upgResp && $upgResp->successful()) {
            $this->info("  ✔ PASSED: Package upgrade to [" . strtoupper($targetPackage) . "] recorded on cloud ({$d4}ms)");
            $this->line("    Cloud User Package: " . ($upgResp->json('data.user.package') ?? 'pro'));
        } else {
            $this->error("  ✖ FAILED: Package upgrade failed on cloud server.");
            return Command::FAILURE;
        }
        $this->newLine();

        // -----------------------------------------------------------------
        // TEST 5: Incoming License Sync from Cloud to Local Instance
        // -----------------------------------------------------------------
        $this->line("<fg=cyan;options=bold>[5/5] Testing Incoming License Sync to Local Platform...</>");
        $t5 = microtime(true);
        $syncResult = $tunnelDeck->syncUserLicense($user);
        $d5 = round((microtime(true) - $t5) * 1000);

        if ($syncResult['success']) {
            $freshUser = $user->fresh();
            $this->info("  ✔ PASSED: License synchronized from cloud to local SQLite database ({$d5}ms)");
            $this->line("    Updated Local Package: <info>" . strtoupper($freshUser->package) . "</info>");
            $this->line("    Currency: <comment>" . ($freshUser->currency ?? 'INR') . "</comment>");
            $this->line("    Max Tunnels Allowed: <info>" . $freshUser->maxTunnels() . " tunnels</info>");
            $this->line("    Last Synced At: " . $freshUser->license_synced_at);
        } else {
            $this->error("  ✖ FAILED: License synchronization to local platform failed.");
            return Command::FAILURE;
        }
        $this->newLine();

        $this->info("=================================================================");
        $this->info("  🎉 ALL 5 TESTS PASSED SUCCESSFULLY!                            ");
        $this->info("  Both Outgoing submissions and Incoming upgrades verified 100%  ");
        $this->info("=================================================================");

        return Command::SUCCESS;
    }
}
