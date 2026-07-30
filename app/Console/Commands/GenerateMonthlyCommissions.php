<?php

namespace App\Console\Commands;

use App\Services\CommissionService;
use Illuminate\Console\Command;

class GenerateMonthlyCommissions extends Command
{
    protected $signature = 'commissions:generate-monthly {period? : YYYY-MM period, defaults to current month}';

    protected $description = 'Create pending monthly commission rows for referred active tenants';

    public function handle(CommissionService $commissions): int
    {
        $period = $this->argument('period') ?? now()->format('Y-m');
        $count = $commissions->generateMonthlyPendingCommissions($period);

        $this->info("Created {$count} pending monthly commission row(s) for {$period}.");

        return self::SUCCESS;
    }
}
