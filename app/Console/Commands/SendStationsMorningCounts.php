<?php

namespace App\Console\Commands;

use App\Services\StationsMorningCountService;
use Illuminate\Console\Command;

class SendStationsMorningCounts extends Command
{
    protected $signature = 'stations:morning-count';

    protected $description = 'Ping doctors with visit vs intervention waiting counts (Stations tenants)';

    public function handle(StationsMorningCountService $service): int
    {
        $count = $service->dispatchForAllTenants();

        $this->info("Queued morning count for {$count} tenant(s).");

        return self::SUCCESS;
    }
}
