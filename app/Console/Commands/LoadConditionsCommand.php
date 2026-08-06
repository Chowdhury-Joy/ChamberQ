<?php

namespace App\Console\Commands;

use App\Models\Condition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadConditionsCommand extends Command
{
    protected $signature = 'conditions:load
        {path? : CSV path (defaults to data/condition-list-draft.csv)}
        {--fresh : Delete existing conditions before loading}';

    protected $description = 'Load the curated condition master list from CSV';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('data/condition-list-draft.csv');

        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('condition_usages')->delete();
            Condition::query()->delete();
            $this->warn('Existing conditions cleared.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Could not open: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->error('CSV is empty.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2 || trim($row[0]) === '') {
                continue;
            }

            [$code, $name, $aliasesRaw, $icd10, $category] = array_pad($row, 5, null);

            $aliases = collect(explode('|', (string) $aliasesRaw))
                ->map(fn (string $alias) => trim($alias))
                ->filter()
                ->values()
                ->all();

            $condition = Condition::query()->updateOrCreate(
                ['code' => trim($code)],
                [
                    'name' => trim($name),
                    'aliases' => $aliases,
                    'icd10_unverified' => filled($icd10) ? trim($icd10) : null,
                    'category' => filled($category) ? trim($category) : null,
                ]
            );

            if ($condition->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Total in database', Condition::count()],
            ]
        );

        return self::SUCCESS;
    }
}
