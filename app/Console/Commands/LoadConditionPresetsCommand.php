<?php

namespace App\Console\Commands;

use App\Models\Condition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Load starter advice and investigations onto the condition catalogue.
 *
 * Separate from `conditions:load` so the 244-row master list stays a list of
 * diagnoses, and the clinical text that hangs off it can be rewritten without
 * touching codes or aliases.
 *
 * Advice is stored in both languages because it is the one field written for
 * the patient rather than the doctor. Everything else in a Bangladeshi
 * prescription is English by convention; "drink plenty of water" is not,
 * because the person who has to follow it reads Bangla.
 */
class LoadConditionPresetsCommand extends Command
{
    protected $signature = 'condition-presets:load
        {path? : CSV path (defaults to data/condition-presets.csv)}
        {--clear : Blank every preset before loading}';

    protected $description = 'Load starter advice and investigations for each diagnosis';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('data/condition-presets.csv');

        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open: {$path}");

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            Condition::query()->update(['default_advice' => null, 'default_tests' => null]);
            $this->warn('Existing presets cleared.');
        }

        $applied = 0;
        $held = 0;
        $unknown = [];
        $seenHeader = false;

        DB::beginTransaction();

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $code = trim((string) ($row[0] ?? ''));

            if ($code === '' || str_starts_with($code, '#')) {
                continue;
            }

            if (! $seenHeader) {
                $seenHeader = true;

                continue;
            }

            [, $advice, $adviceBn, $tests, $hold] = array_pad($row, 5, null);

            if (in_array(mb_strtolower(trim((string) $hold)), ['hold', 'no', 'skip', 'off'], true)) {
                $held++;

                continue;
            }

            $condition = Condition::query()->where('code', $code)->first();

            if (! $condition) {
                $unknown[] = $code;

                continue;
            }

            $condition->forceFill([
                // Both languages live in one column as a JSON pair so the pad
                // can hand the doctor whichever one he is reading, without a
                // second table for two strings.
                'default_advice' => $this->encodeAdvice($advice, $adviceBn),
                'default_tests' => filled($tests) ? trim((string) $tests) : null,
            ])->save();

            $applied++;
        }

        DB::commit();
        fclose($handle);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Presets applied', $applied],
                ['On hold', $held],
                ['Unknown condition codes', count($unknown)],
            ]
        );

        if ($unknown !== []) {
            $this->newLine();
            $this->line('<fg=gray>No condition for: '.implode(', ', $unknown).'</>');
        }

        return self::SUCCESS;
    }

    private function encodeAdvice(?string $english, ?string $bangla): ?string
    {
        $pair = array_filter([
            'en' => filled($english) ? trim($english) : null,
            'bn' => filled($bangla) ? trim($bangla) : null,
        ]);

        return $pair === [] ? null : json_encode($pair, JSON_UNESCAPED_UNICODE);
    }
}
