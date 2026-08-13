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
        $conditions = $this->call('condition-presets:load') === self::SUCCESS ? $conditions : self::FAILURE;
        $medicines = $this->call('medicines:load');
        // Runs last: it writes onto the medicine rows the previous step just
        // upserted, and only the rows a doctor marked approved.
        $dosing = $this->call('dosing-defaults:load');
        // Independent of the medicine catalogue — it keys on ingredient names,
        // not on catalogue rows — but loaded here so a fresh server cannot end
        // up with a prescription pad that silently checks nothing.
        $interactions = $this->call('interactions:load');

        return ($conditions === self::SUCCESS && $medicines === self::SUCCESS
            && $dosing === self::SUCCESS && $interactions === self::SUCCESS)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
