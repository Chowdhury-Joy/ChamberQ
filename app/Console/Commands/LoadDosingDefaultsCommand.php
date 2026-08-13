<?php

namespace App\Console\Commands;

use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Medicine;
use App\Support\PrescriptionTiming;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apply per-generic dosing defaults onto the medicine catalogue.
 *
 * Kept separate from `medicines:load` on purpose. That command loads
 * BDDrugBank, which is a product list and carries no dosing at all; this one
 * loads a clinical starting pattern written by hand. Splitting them means a
 * catalogue refresh cannot silently overwrite the defaults, and the
 * provenance of each column stays obvious.
 *
 * Three guard rails, all deliberate:
 *  - a value outside the closed frequency/duration/timing vocabularies is
 *    rejected rather than stored, because the pad renders those as chips and
 *    a typo would surface as an unfixable cell;
 *  - only oral forms are touched, so an oral pattern never lands on an
 *    injection, an eye drop or a nappy-rash cream;
 *  - a combination product never inherits a single-ingredient default, so
 *    "Diclofenac Sodium + Misoprostol" is left alone by the Diclofenac row.
 */
class LoadDosingDefaultsCommand extends Command
{
    protected $signature = 'dosing-defaults:load
        {path? : CSV path (defaults to data/dosing-defaults.csv)}
        {--dry-run : Report what would change without writing}
        {--clear : Blank every dosing default in the catalogue first}';

    protected $description = 'Apply starter dosing defaults to the medicine catalogue';

    /**
     * Forms a swallowed adult pattern can legitimately describe.
     *
     * Deliberately excludes `drops` and `solution`: both are mostly eye, ear
     * and nasal preparations in this catalogue, and "1+1+1 after food" is
     * nonsense on either.
     *
     * @var list<string>
     */
    private const ORAL_FORMS = ['tablet', 'capsule', 'syrup', 'sachet'];

    /**
     * Salt, ester and hydrate words the catalogue appends to a generic name.
     *
     * Stripped only when comparing: the stored name is never rewritten. This
     * is what lets one `Pantoprazole` row reach `Pantoprazole Sodium`.
     *
     * @var list<string>
     */
    private const SALT_WORDS = [
        'hydrochloride', 'dihydrochloride', 'hydrobromide', 'hydrogen',
        'sodium', 'potassium', 'calcium', 'magnesium', 'aluminium',
        'sulphate', 'sulfate', 'phosphate', 'acetate', 'maleate', 'tartrate',
        'bitartrate', 'succinate', 'besylate', 'besilate', 'mesylate',
        'mesilate', 'fumarate', 'oxalate', 'malate', 'lactate', 'gluconate',
        'orotate', 'stearate', 'palmitate', 'valerate', 'propionate',
        'dipropionate', 'furoate', 'citrate', 'carbonate', 'bicarbonate',
        'chloride', 'bromide', 'pivoxil', 'axetil', 'proxetil', 'medoxomil',
        'cilexetil', 'propanediol', 'hemihydrate', 'trihydrate', 'dihydrate',
        'monohydrate', 'hydrate', 'anhydrous', 'micronized', 'micronised',
        'usp', 'bp', 'inn', 'base',
    ];

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('data/dosing-defaults.csv');

        if (! is_readable($path)) {
            $this->error("CSV not readable: {$path}");

            return self::FAILURE;
        }

        $parsed = $this->readRows($path);

        if ($parsed === null) {
            return self::FAILURE;
        }

        [$defaults, $held, $rejected] = $parsed;

        foreach ($rejected as $problem) {
            $this->warn($problem);
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->line('Dry run — nothing written.');
        } elseif ($this->option('clear')) {
            Medicine::query()->update([
                'default_frequency' => null,
                'default_duration' => null,
                'default_timing' => null,
            ]);
            $this->warn('Existing dosing defaults cleared.');
        }

        $updates = $this->resolveUpdates($defaults);

        $touched = 0;

        DB::beginTransaction();

        foreach ($updates as $signature => $ids) {
            $touched += count($ids);

            if (! $dryRun) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    Medicine::query()->whereIn('id', $chunk)->update($defaults[$signature]);
                }
            }
        }

        $dryRun ? DB::rollBack() : DB::commit();

        $unmatched = array_values(array_diff(array_keys($defaults), array_keys($updates)));

        $this->table(
            ['Metric', 'Count'],
            [
                ['Generics in sheet', count($defaults) + $held],
                ['On hold (left blank)', $held],
                ['Rejected values', count($rejected)],
                ['Generics with no oral catalogue match', count($unmatched)],
                ['SKUs given defaults', $touched],
            ]
        );

        if ($unmatched !== []) {
            $this->newLine();
            $this->line('<fg=gray>No oral catalogue row for: '.implode(', ', $unmatched).'</>');
        }

        return self::SUCCESS;
    }

    /**
     * Map each sheet generic to the catalogue rows it legitimately describes.
     *
     * Matching runs over the oral catalogue once rather than issuing a query
     * per generic: 172 `LIKE` scans across 24k rows is slower than one pass
     * and cannot express "same drug, different salt" anyway.
     *
     * @param  array<string, array<string, string|null>>  $defaults
     * @return array<string, list<string>>
     */
    private function resolveUpdates(array $defaults): array
    {
        $matches = [];

        Medicine::query()
            ->whereIn('form', self::ORAL_FORMS)
            ->whereNotNull('generic_name')
            ->select(['id', 'generic_name'])
            ->chunkById(2000, function ($chunk) use ($defaults, &$matches): void {
                foreach ($chunk as $medicine) {
                    $key = $this->comparableGeneric($medicine->generic_name);

                    if ($key === '' || ! isset($defaults[$key])) {
                        continue;
                    }

                    $matches[$key][] = $medicine->id;
                }
            });

        return $matches;
    }

    /**
     * Reduce a generic name to something two spellings of the same drug share.
     *
     * `Pantoprazole Sodium` and `Pantoprazole` collapse to the same string;
     * `Diclofenac Sodium + Misoprostol` keeps its second ingredient and so
     * only ever matches a sheet row that names both.
     */
    private function comparableGeneric(?string $name): string
    {
        $value = mb_strtolower(trim((string) $name));

        if ($value === '') {
            return '';
        }

        // "Zinc Oxide [For diaper rash]" — the bracket is a use note, not part
        // of the drug name.
        $value = (string) preg_replace('/\[[^\]]*\]/', ' ', $value);
        $value = str_replace(['&', ' and '], '+', $value);

        $parts = array_filter(array_map('trim', explode('+', $value)));

        $cleaned = array_map(function (string $part): string {
            $words = preg_split('/[^a-z0-9]+/', $part, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            // The first word is never stripped: in "Zinc Sulfate Monohydrate"
            // and "Sodium Valproate" the salt word *is* the drug, and only its
            // position distinguishes that from "Pantoprazole Sodium".
            $kept = array_values(array_filter(
                $words,
                fn (string $word, int $i) => $i === 0 || ! in_array($word, self::SALT_WORDS, true),
                ARRAY_FILTER_USE_BOTH,
            ));

            return implode(' ', $kept);
        }, $parts);

        sort($cleaned);

        return implode(' + ', $cleaned);
    }

    /**
     * @return array{0: array<string, array<string, string|null>>, 1: int, 2: list<string>}|null
     */
    private function readRows(string $path): ?array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open: {$path}");

            return null;
        }

        $defaults = [];
        $held = 0;
        $rejected = [];
        $lineNumber = 0;
        $seenHeader = false;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNumber++;

            $first = trim((string) ($row[0] ?? ''));

            // Comment lines carry the rationale; the sheet is meant to be read
            // by a person, not only parsed.
            if ($first === '' || str_starts_with($first, '#')) {
                continue;
            }

            if (! $seenHeader) {
                $seenHeader = true;

                continue;
            }

            [$generic, $frequency, $duration, $timing, $hold] = array_pad($row, 5, null);

            if ($this->isHeld($hold)) {
                $held++;

                continue;
            }

            $normalizedFrequency = $this->matchPreset($frequency, VisitNotesFormSchema::FREQUENCY_PRESETS);
            $normalizedDuration = $this->matchPreset($duration, VisitNotesFormSchema::DURATION_PRESETS);
            $normalizedTiming = PrescriptionTiming::normalize(is_string($timing) ? $timing : null);

            foreach ([['frequency', $frequency, $normalizedFrequency], ['duration', $duration, $normalizedDuration], ['timing', $timing, $normalizedTiming]] as [$label, $raw, $normalized]) {
                if (filled($raw) && $normalized === null) {
                    $rejected[] = "Line {$lineNumber} ({$generic}): {$label} \"".trim((string) $raw).'" is not an allowed value — left blank.';
                }
            }

            if ($normalizedFrequency === null && $normalizedDuration === null && $normalizedTiming === null) {
                continue;
            }

            $key = $this->comparableGeneric($generic);

            if ($key === '') {
                continue;
            }

            $defaults[$key] = [
                'default_frequency' => $normalizedFrequency,
                'default_duration' => $normalizedDuration,
                'default_timing' => $normalizedTiming,
            ];
        }

        fclose($handle);

        return [$defaults, $held, $rejected];
    }

    private function isHeld(?string $cell): bool
    {
        return in_array(mb_strtolower(trim((string) $cell)), ['hold', 'no', 'skip', 'off'], true);
    }

    /**
     * @param  list<string>  $presets
     */
    private function matchPreset(?string $value, array $presets): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        // The sheet is edited in a spreadsheet, where typing ½ is awkward.
        $trimmed = str_replace('1/2', '½', $trimmed);

        foreach ($presets as $preset) {
            if (mb_strtolower($preset) === mb_strtolower($trimmed)) {
                return $preset;
            }
        }

        return null;
    }
}
