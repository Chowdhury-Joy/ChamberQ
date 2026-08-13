<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use App\Support\DrugIngredients;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Can our medicines be checked for interactions at all?
 *
 * A read-only measurement, not a feature. Interaction data keys on single
 * clean ingredients; our catalogue stores free-text generic names, a quarter
 * of which are combination products carrying salt names. Before any
 * interaction database is licensed or built, we need one number: **what share
 * of the catalogue could be checked**.
 *
 * The number that matters is per *medicine*, not per ingredient. A combination
 * product is only checkable when **every** one of its ingredients resolves —
 * one unmatched ingredient and the whole prescription line is unverifiable.
 *
 * Why this gate exists: an unmatched drug produces no warning, and a doctor
 * who sees no warning concludes there is nothing to worry about. A checker
 * that silently skips drugs is worse than none, because it replaces the
 * doctor's own caution with false confidence.
 *
 * RxNorm is used only to answer "is this a recognised ingredient name" — a
 * spelling question, not a clinical one. It is free, needs no licence, and
 * handles non-US names (`paracetamol` resolves, not just `acetaminophen`).
 * The interaction *content*, if this feature is ever built, is a separate and
 * deliberately Bangladeshi decision — see the plan and `decisions.md`.
 *
 * Writes nothing to the database.
 */
class DrugCoverageReportCommand extends Command
{
    protected $signature = 'drugs:coverage-report
        {--limit= : Only test this many distinct ingredients (for a quick look)}
        {--fresh : Ignore the cached lookups and re-query RxNorm}
        {--retry-failed : Re-query only the ingredients that previously failed}
        {--offline : Skip RxNorm entirely; report only the parsing statistics}';

    protected $description = 'Measure what share of the medicine catalogue could support interaction checking';

    private const CACHE_FILE = 'drug-coverage/rxnorm-lookups.json';

    private const REPORT_FILE = 'drug-coverage/unmatched-ingredients.csv';

    /** NLM allows 20 requests/second per IP; stay well under it. */
    private const REQUEST_DELAY_MICROSECONDS = 120_000;


    /**
     * Local or British spellings RxNorm does not carry as synonyms.
     *
     * Deliberately tiny and only for names proven to resolve under their other
     * spelling — this is not a place to guess a drug is "probably" another one.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'b12' => 'cyanocobalamin',
        'vitamin b12' => 'cyanocobalamin',
        'b6' => 'pyridoxine',
        'b1' => 'thiamine',
        'guaiphenasine' => 'guaifenesin',
        'guaiphenesin' => 'guaifenesin',
        'frusemide' => 'furosemide',
        'paracetamol' => 'acetaminophen',
        'salbutamol' => 'albuterol',
        'lignocaine' => 'lidocaine',
        'adrenaline' => 'epinephrine',
        'noradrenaline' => 'norepinephrine',
    ];

    /** @var array<string, ?string> ingredient => rxcui|null */
    private array $cache = [];

    public function handle(): int
    {
        $this->cache = $this->option('fresh') ? [] : $this->loadCache();

        if ($this->option('retry-failed')) {
            // Misses are cached too, so tuning the salt list or the alias map
            // would otherwise change nothing until a full 25-minute re-run.
            $this->cache = array_filter($this->cache, fn (?string $v): bool => $v !== null);
        }

        $medicines = Medicine::query()
            ->select(['brand_name', 'generic_name', 'priority'])
            ->whereNotNull('generic_name')
            ->get();

        if ($medicines->isEmpty()) {
            $this->error('No medicines in the catalogue. Run `php artisan catalogues:load` first.');

            return self::FAILURE;
        }

        // ingredient => how many catalogue rows depend on it resolving
        $ingredientWeight = [];
        // medicine row => its list of cleaned ingredients
        $rows = [];

        foreach ($medicines as $medicine) {
            $ingredients = DrugIngredients::split($medicine->generic_name);

            if ($ingredients === []) {
                continue;
            }

            $rows[] = ['medicine' => $medicine, 'ingredients' => $ingredients];

            foreach ($ingredients as $ingredient) {
                $ingredientWeight[$ingredient] = ($ingredientWeight[$ingredient] ?? 0) + 1;
            }
        }

        $distinct = array_keys($ingredientWeight);
        arsort($ingredientWeight);

        $this->line('');
        $this->info('Catalogue');
        $this->line(sprintf('  %d medicine rows, %d distinct ingredients after splitting', count($rows), count($distinct)));

        if ($this->option('offline')) {
            $this->reportParsingOnly($ingredientWeight);

            return self::SUCCESS;
        }

        $toLookUp = $this->option('limit')
            ? array_slice($distinct, 0, (int) $this->option('limit'))
            : $distinct;

        $this->resolveAll($toLookUp);
        $this->saveCache();

        return $this->report($rows, $ingredientWeight);
    }


    

    /**
     * @param  list<string>  $ingredients
     */
    private function resolveAll(array $ingredients): void
    {
        $pending = array_values(array_filter(
            $ingredients,
            fn (string $i): bool => ! array_key_exists($i, $this->cache),
        ));

        if ($pending === []) {
            $this->line('  all lookups already cached');

            return;
        }

        $this->line('');
        $this->info(sprintf('Resolving %d ingredients against RxNorm', count($pending)));
        $bar = $this->output->createProgressBar(count($pending));
        $bar->start();

        foreach ($pending as $index => $ingredient) {
            $this->cache[$ingredient] = $this->resolve($ingredient);
            $bar->advance();

            // Checkpoint so a network drop does not throw away the whole run.
            if ($index % 100 === 99) {
                $this->saveCache();
            }

            usleep(self::REQUEST_DELAY_MICROSECONDS);
        }

        $bar->finish();
        $this->line('');
    }

    /**
     * Exact name, then salt-stripped, then a scored approximate match.
     */
    private function resolve(string $ingredient): ?string
    {
        if ($rxcui = $this->exactMatch($ingredient)) {
            return $rxcui;
        }

        $stripped = DrugIngredients::saltStripped($ingredient);

        if ($stripped !== null && ($rxcui = $this->exactMatch($stripped))) {
            return $rxcui;
        }

        foreach ([$ingredient, $stripped] as $candidate) {
            $alias = self::ALIASES[$candidate ?? ''] ?? null;

            if ($alias !== null && ($rxcui = $this->exactMatch($alias))) {
                return $rxcui;
            }
        }

        return $this->approximateMatch($ingredient);
    }

    private function exactMatch(string $name): ?string
    {
        $response = $this->get('https://rxnav.nlm.nih.gov/REST/rxcui.json', [
            'name' => $name,
            'search' => 2, // normalized string match
        ]);

        return $response['idGroup']['rxnormId'][0] ?? null;
    }

    /**
     * RxNorm's fuzzy match. The score threshold is deliberately high: a loose
     * match here would claim a drug is checkable when it is really a different
     * molecule, which is the one outcome this whole exercise exists to avoid.
     */
    private function approximateMatch(string $name): ?string
    {
        $response = $this->get('https://rxnav.nlm.nih.gov/REST/approximateTerm.json', [
            'term' => $name,
            'maxEntries' => 1,
        ]);

        $candidate = $response['approximateGroup']['candidate'][0] ?? null;

        if (! $candidate || ! isset($candidate['score'])) {
            return null;
        }

        return (float) $candidate['score'] >= 50.0 ? ($candidate['rxcui'] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $url, array $query): array
    {
        try {
            $response = Http::timeout(15)->retry(2, 500)->get($url, $query);

            return $response->successful() ? (array) $response->json() : [];
        } catch (\Throwable $e) {
            $this->newLine();
            $this->warn('  lookup failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  list<array{medicine: Medicine, ingredients: list<string>}>  $rows
     * @param  array<string, int>  $ingredientWeight
     */
    private function report(array $rows, array $ingredientWeight): int
    {
        $resolvedIngredients = array_filter($this->cache, fn (?string $v): bool => $v !== null);

        $checkable = 0;
        $partly = 0;

        foreach ($rows as $row) {
            $known = array_filter(
                $row['ingredients'],
                fn (string $i): bool => ! empty($this->cache[$i] ?? null),
            );

            if (count($known) === count($row['ingredients'])) {
                $checkable++;
            } elseif ($known !== []) {
                $partly++;
            }
        }

        $total = count($rows);
        $pct = $total > 0 ? round($checkable / $total * 100, 1) : 0.0;

        $this->line('');
        $this->info('Ingredients');
        $this->line(sprintf(
            '  %d of %d resolved (%.1f%%)',
            count($resolvedIngredients),
            count($this->cache),
            count($this->cache) > 0 ? count($resolvedIngredients) / count($this->cache) * 100 : 0,
        ));

        $this->line('');
        $this->info('Medicines — the number that decides the feature');
        $this->table(
            ['Outcome', 'Rows', 'Share'],
            [
                ['Fully checkable (every ingredient known)', $checkable, sprintf('%.1f%%', $pct)],
                ['Partly known (would need "not checked")', $partly, sprintf('%.1f%%', $total > 0 ? $partly / $total * 100 : 0)],
                ['Not checkable at all', $total - $checkable - $partly, sprintf('%.1f%%', $total > 0 ? ($total - $checkable - $partly) / $total * 100 : 0)],
            ],
        );

        $unmatched = [];

        foreach ($ingredientWeight as $ingredient => $weight) {
            if (array_key_exists($ingredient, $this->cache) && empty($this->cache[$ingredient])) {
                $unmatched[] = [$ingredient, $weight];
            }
        }

        if ($unmatched !== []) {
            $this->line('');
            $this->info('Top unmatched ingredients (by how many catalogue rows they affect)');
            $this->table(['Ingredient', 'Rows affected'], array_slice($unmatched, 0, 20));
            $this->writeUnmatchedCsv($unmatched);
            $this->line('  full list: '.Storage::path(self::REPORT_FILE));
        }

        $this->line('');
        $this->info('Verdict');
        $this->line(match (true) {
            $pct >= 95 => '  Above 95% — the checker can be honest. Proceed to the pair list.',
            $pct >= 70 => '  70–95% — viable ONLY if the screen marks which medicines it could not check.',
            default => '  Below 70% — do not build this. It would stay silent on too many drugs.',
        });
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $ingredientWeight
     */
    private function reportParsingOnly(array $ingredientWeight): void
    {
        $this->line('');
        $this->info('Most common ingredients (no lookups performed)');
        $this->table(
            ['Ingredient', 'Rows'],
            array_map(null, array_slice(array_keys($ingredientWeight), 0, 20), array_slice(array_values($ingredientWeight), 0, 20)),
        );
    }

    /**
     * @param  list<array{0: string, 1: int}>  $unmatched
     */
    private function writeUnmatchedCsv(array $unmatched): void
    {
        $lines = ["ingredient,rows_affected"];

        foreach ($unmatched as [$ingredient, $weight]) {
            $lines[] = '"'.str_replace('"', '""', $ingredient).'",'.$weight;
        }

        Storage::put(self::REPORT_FILE, implode("\n", $lines)."\n");
    }

    /**
     * @return array<string, ?string>
     */
    private function loadCache(): array
    {
        if (! Storage::exists(self::CACHE_FILE)) {
            return [];
        }

        return (array) json_decode((string) Storage::get(self::CACHE_FILE), true);
    }

    private function saveCache(): void
    {
        Storage::put(self::CACHE_FILE, json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
