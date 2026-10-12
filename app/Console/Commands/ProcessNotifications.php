<?php

namespace App\Console\Commands;

use App\Domain\Notifications\ProcessNotificationJobs;
use Illuminate\Console\Command;

class ProcessNotifications extends Command
{
    protected $signature = 'fleetpilot:notifications';

    protected $description = 'Send pending FleetPilot notification jobs';

    public function handle(ProcessNotificationJobs $jobs): int
    {
        $sent = $jobs->run();
        $this->info("Sent {$sent} notification jobs.");

        return self::SUCCESS;
    }
}
