<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TunnelManagerService;

class SyncTunnels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tunnels:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize and recover tunnel process states (e.g. after system reboot)';

    /**
     * Execute the console command.
     */
    public function handle(TunnelManagerService $manager)
    {
        $this->info('Starting tunnel process state synchronization...');
        
        $manager->syncTunnelStates();
        
        $this->info('State synchronization completed successfully.');
        return 0;
    }
}
