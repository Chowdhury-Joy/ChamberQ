<?php

namespace App\Console\Commands;

use App\Models\DrugInteraction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadDrugInteractionsCommand extends Command
{
    protected $signature = 'interactions:load
        {path? : CSV path (defaults to data/drug-interactions.csv)}
        {--prune : Delete pairs absent from the CSV}';

    protected $description = 'Load the drug interaction pair list from CSV';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('data/drug-interactions.csv');

        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open: {$path}");

            return self::FAILURE;
        }

        fgetcsv($handle, escape: '');

        $created = 0;
        $updated = 0;
        $seen = [];

        DB::beginTransaction();

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (count($row) < 4 || trim((string) $row[0]) === '') {
                continue;
            }

            [$a, $b, $severity, $effect, $action, $source] = array_pad($row, 6, null);

            // Alphabetical, so a lookup never depends on which drug the doctor
            // happened to type first.
            $pair = [mb_strtolower(trim((string) $a)), mb_strtolower(trim((string) $b))];
            sort($pair);

            $seen[] = implode('|', $pair);

            $interaction = DrugInteraction::query()->updateOrCreate(
                ['ingredient_a' => $pair[0], 'ingredient_b' => $pair[1]],
                [
                    'severity' => in_array($severity, DrugInteraction::SEVERITIES, true)
                        ? $severity
                        : DrugInteraction::SEVERITY_SERIOUS,
                    'effect' => trim((string) $effect),
                    'action' => filled($action) ? trim((string) $action) : null,
                    'source' => filled($source) ? trim((string) $source) : null,
                ],
            );

            $interaction->wasRecentlyCreated ? $created++ : $updated++;
        }

        DB::commit();
        fclose($handle);

        $pruned = 0;

        if ($this->option('prune')) {
            $pruned = DrugInteraction::query()
                ->get()
                ->filter(fn (DrugInteraction $i): bool => ! in_array(
                    $i->ingredient_a.'|'.$i->ingredient_b,
                    $seen,
                    true,
                ))
                ->each(fn (DrugInteraction $i) => $i->delete())
                ->count();
        }

        $unreviewed = DrugInteraction::query()->whereNull('reviewed_at')->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Pruned', $pruned],
                ['Total pairs', DrugInteraction::count()],
                ["Never date-checked", $unreviewed],
            ],
        );

        // Said out loud on every run, not buried in a doc. This list is
        // compiled from general pharmacology, not a licensed clinical database,
        // and by owner decision (2026-08-12) no clinician is named against it.
        // What protects the doctor is therefore the standing disclaimer shown
        // beside every warning — so if that ever stops rendering, this feature
        // is making a claim it cannot support.
        $this->warn('  Reference list, not a licensed clinical database, and knowingly incomplete.');
        $this->warn('  Every warning must display RxSafety::DISCLAIMER alongside it.');

        if ($unreviewed > 0) {
            $this->warn("  {$unreviewed} pair(s) have no check date recorded.");
        }

        return self::SUCCESS;
    }
}
