<?php

namespace App\Console\Commands;

use App\Services\TunnelDeckService;
use Illuminate\Console\Command;

class ResetToDefaultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tunneldeck:reset-defaults';

    /**
     * The console command description.
     */
    protected $description = 'Reset TunnelDeck Lite database and configuration to factory defaults';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Resetting TunnelDeck Lite database to factory defaults...');

        $result = TunnelDeckService::resetToDefaults();

        $this->info('✔ ' . ($result['message'] ?? 'Database reset to defaults.'));

        return Command::SUCCESS;
    }
}

