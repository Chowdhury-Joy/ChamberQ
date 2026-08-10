<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Load both global clinical catalogues in one idempotent step.
 *
 * Migrations create empty `conditions` and `medicines` tables; the CSV importers
 * fill them. Forgetting the second step leaves prescription pickers blank with
 * no error — see ProductionReadiness::MEDICINE_CATALOGUE.
 */
class LoadCataloguesCommand extends Command
{
    protected $signature = 'catalogues:load';

    protected $description = 'Load curated condition and medicine master lists from CSV';

    public function handle(): int
    {
        $conditions = $this->call('conditions:load');
        $medicines = $this->call('medicines:load');

        return ($conditions === self::SUCCESS && $medicines === self::SUCCESS)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
