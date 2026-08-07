<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\Medicine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadMedicinesCommand extends Command
{
    protected $signature = 'medicines:load
        {path? : CSV path (defaults to data/medicine-list-draft.csv)}
        {--fresh : Delete existing medicines and all medicine_usages before loading}
        {--prune : Delete catalogue rows absent from the CSV (keeps medicine_usages)}';

    protected $description = 'Load the curated medicine master list from CSV';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('data/medicine-list-draft.csv');

        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('medicine_usages')->delete();
            Medicine::query()->delete();
            $this->warn('Existing medicines and usages cleared.');
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
        $brandsInCsv = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2 || trim($row[0]) === '') {
                continue;
            }

            [$brand, $generic, $strength, $form, $aliasesRaw, $category, $practiceTypesRaw] = array_pad($row, 7, null);

            $normalizedBrand = mb_strtoupper(trim($brand));
            $brandsInCsv[] = $normalizedBrand;

            $aliases = collect(explode('|', (string) $aliasesRaw))
                ->map(fn (string $alias) => trim($alias))
                ->filter()
                ->values()
                ->all();

            $practiceTypes = collect(explode('|', (string) $practiceTypesRaw))
                ->map(fn (string $type) => trim($type))
                ->filter()
                ->values()
                ->all();

            if ($practiceTypes === []) {
                $practiceTypes = [Doctor::PRACTICE_GENERAL];

                if (trim((string) $category) === 'Dermatology') {
                    $practiceTypes[] = Doctor::PRACTICE_DERMATOLOGIST;
                }

                if (trim((string) $category) === 'Dental') {
                    $practiceTypes[] = Doctor::PRACTICE_DENTIST;
                }

                if (trim((string) $category) === 'Gynecology') {
                    $practiceTypes[] = Doctor::PRACTICE_GYNECOLOGIST;
                }
            }

            $medicine = Medicine::query()->updateOrCreate(
                ['brand_name' => $normalizedBrand],
                [
                    'generic_name' => filled($generic) ? trim($generic) : null,
                    'default_strength' => filled($strength) ? trim($strength) : null,
                    'form' => filled($form) ? trim($form) : null,
                    'aliases' => $aliases,
                    'category' => filled($category) ? trim($category) : null,
                    'practice_types' => $practiceTypes,
                ]
            );

            if ($medicine->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        $pruned = 0;

        if ($this->option('prune')) {
            $brandsInCsv = array_values(array_unique($brandsInCsv));
            $pruned = Medicine::query()
                ->whereNotIn('brand_name', $brandsInCsv)
                ->delete();
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Pruned', $pruned],
                ['Total in database', Medicine::count()],
            ]
        );

        return self::SUCCESS;
    }
}
