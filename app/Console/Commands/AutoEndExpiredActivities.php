<?php

namespace App\Console\Commands;

use App\Services\SessionActivityService;
use Illuminate\Console\Command;

class AutoEndExpiredActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activities:auto-end';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically end activities that have passed their scheduled end time';

    /**
     * Execute the console command.
     */
    public function handle(SessionActivityService $sessionActivityService): int
    {
        $this->info('Checking for expired activities...');
        
        $count = $sessionActivityService->autoEndExpiredActivities();
        
        if ($count > 0) {
            $this->info("Successfully auto-ended {$count} activity(ies).");
        } else {
            $this->info('No expired activities found.');
        }
        
        return Command::SUCCESS;
    }
}
