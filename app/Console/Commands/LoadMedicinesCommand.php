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

        // 24k upserts is 24k round trips on the default connection; one
        // transaction turns that from minutes into seconds and makes a failed
        // import leave the catalogue untouched rather than half-replaced.
        DB::beginTransaction();

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2 || trim($row[0]) === '') {
                continue;
            }

            [
                $brand, $generic, $strength, $form, $aliasesRaw, $category,
                $practiceTypesRaw, $indications, $manufacturer, $isEssential, $priority,
            ] = array_pad($row, 11, null);

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

            // Keyed on the SKU triple, not the brand: NAPA is a 500 mg tablet,
            // a 120 mg/5 ml syrup, 80 mg/ml drops, three suppositories and an
            // IV infusion. Upserting on brand alone collapsed those to one row
            // and silently dropped 8,656 SKUs across the catalogue — mostly the
            // syrups and drops a chamber GP needs for children.
            $medicine = Medicine::query()->updateOrCreate(
                [
                    'brand_name' => $normalizedBrand,
                    'default_strength' => filled($strength) ? trim($strength) : null,
                    'form' => filled($form) ? trim($form) : null,
                ],
                [
                    'generic_name' => filled($generic) ? trim($generic) : null,
                    'aliases' => $aliases,
                    'category' => filled($category) ? trim($category) : null,
                    'practice_types' => $practiceTypes,
                    'indications' => filled($indications) ? trim($indications) : null,
                    'manufacturer' => filled($manufacturer) ? trim($manufacturer) : null,
                    'is_essential' => (bool) (int) ($isEssential ?? 0),
                    'priority' => (int) ($priority ?? Medicine::TIER_STANDARD),
                ]
            );

            if ($medicine->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        DB::commit();

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
