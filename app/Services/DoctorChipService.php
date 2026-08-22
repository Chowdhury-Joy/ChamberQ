<?php

namespace App\Services;

use App\Models\DoctorChip;
use App\Models\User;
use App\Support\AdviceChips;
use App\Support\HistoryChips;
use Illuminate\Support\Collection;

/**
 * A doctor's Advice and History chips: the shipped defaults, with this
 * doctor's edits, hides and additions applied.
 *
 * The defaults stay in code (`AdviceChips`, `HistoryChips`) so they can be
 * changed and translated with a deploy. The database only ever holds a
 * doctor's *departures* from them — an override row carrying the built-in's
 * key, or a row with no key at all, which is a chip they added.
 */
class DoctorChipService
{
    /**
     * A chip line is a whole sentence of patient advice, not a name — the
     * column is 255 and the desk sends whatever was typed.
     */
    public const MAX_TEXT = 255;

    public const MAX_LABEL = 120;

    /**
     * The shipped chips for a kind, keyed by their stable key.
     *
     * @return array<string, array{key: string, label: string, text: ?string, is_primary: bool}>
     */
    public function defaults(string $kind): array
    {
        $rows = $kind === DoctorChip::KIND_ADVICE
            ? collect(AdviceChips::all())->map(fn (array $chip): array => [
                'key' => $chip['key'],
                'label' => $chip['label'],
                'text' => $chip['text'],
                'is_primary' => true,
            ])
            : collect(HistoryChips::all())->map(fn (array $chip): array => [
                'key' => $chip['key'],
                'label' => $chip['label'],
                'text' => null,
                'is_primary' => $chip['primary'],
            ]);

        return $rows->keyBy('key')->all();
    }

    /**
     * This doctor's chips of one kind, in the order the desk should show them.
     *
     * Built-ins keep their shipped order — a doctor learns where "Rest" is and
     * it must not move — and the chips they added follow, alphabetically, for
     * the same reason `My medicines` is alphabetical: a strip that reorders
     * itself from behaviour the doctor cannot see was ruled out.
     *
     * @return list<array{id: string, chip_id: ?int, key: ?string, label: string, text: string, is_primary: bool, is_default: bool, is_hidden: bool}>
     */
    public function forDoctor(?User $user, string $kind, bool $includeHidden = false): array
    {
        $overrides = $user
            ? $this->rows($user, $kind)
            : collect();

        $byKey = $overrides->whereNotNull('default_key')->keyBy('default_key');

        $chips = [];

        foreach ($this->defaults($kind) as $key => $default) {
            /** @var DoctorChip|null $override */
            $override = $byKey->get($key);

            if ($override?->isHidden() && ! $includeHidden) {
                continue;
            }

            $chips[] = [
                'id' => 'default:'.$key,
                'chip_id' => $override?->id,
                'key' => $key,
                'label' => $override?->label ?? __($default['label']),
                'text' => $override
                    ? $override->insertedText()
                    : ($default['text'] ?? $default['label']),
                'is_primary' => $override ? $override->is_primary : $default['is_primary'],
                'is_default' => true,
                'is_hidden' => (bool) $override?->isHidden(),
            ];
        }

        $custom = $overrides
            ->whereNull('default_key')
            ->when(! $includeHidden, fn (Collection $rows) => $rows->filter(fn (DoctorChip $chip) => ! $chip->isHidden()))
            ->sortBy(fn (DoctorChip $chip) => mb_strtolower($chip->label))
            ->map(fn (DoctorChip $chip): array => [
                'id' => 'chip:'.$chip->id,
                'chip_id' => $chip->id,
                'key' => null,
                'label' => $chip->label,
                'text' => $chip->insertedText(),
                'is_primary' => $chip->is_primary,
                'is_default' => false,
                'is_hidden' => $chip->isHidden(),
            ])
            ->values()
            ->all();

        return [...$chips, ...$custom];
    }

    /**
     * The one chip a page action is acting on, by the id `forDoctor()` gave it.
     *
     * @return array{id: string, chip_id: ?int, key: ?string, label: string, text: string, is_primary: bool, is_default: bool, is_hidden: bool}|null
     */
    public function find(?User $user, string $kind, ?string $id): ?array
    {
        if (blank($id)) {
            return null;
        }

        return collect($this->forDoctor($user, $kind, includeHidden: true))
            ->firstWhere('id', $id);
    }

    /**
     * Create or update a chip.
     *
     * `$id` is null for a new chip, `default:<key>` to override a built-in, or
     * `chip:<id>` to edit one the doctor added.
     *
     * @param  array{label?: ?string, text?: ?string, is_primary?: bool}  $data
     */
    public function save(User $user, string $kind, ?string $id, array $data): ?DoctorChip
    {
        $label = $this->clean($data['label'] ?? null, self::MAX_LABEL);

        if ($label === null) {
            return null;
        }

        $attributes = [
            'label' => $label,
            'text_bn' => $this->clean($data['text'] ?? null, self::MAX_TEXT),
            'is_primary' => (bool) ($data['is_primary'] ?? true),
            // Editing a chip the doctor had hidden is how they bring it back;
            // leaving it hidden would save an edit they never see again.
            'hidden_at' => null,
        ];

        if (is_string($id) && str_starts_with($id, 'chip:')) {
            $chip = $this->rows($user, $kind)->firstWhere('id', (int) substr($id, 5));

            if (! $chip) {
                return null;
            }

            $chip->update($attributes);

            return $chip;
        }

        $defaultKey = is_string($id) && str_starts_with($id, 'default:')
            ? substr($id, 8)
            : null;

        if ($defaultKey !== null && ! array_key_exists($defaultKey, $this->defaults($kind))) {
            return null;
        }

        return DoctorChip::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'kind' => $kind,
                'default_key' => $defaultKey,
            ],
            $attributes,
        );
    }

    /**
     * Take a chip off the desk.
     *
     * A built-in cannot be deleted — it lives in code — so it is remembered as
     * a hidden row and can be restored. A chip the doctor added is theirs to
     * delete outright; nothing already prescribed refers to it.
     */
    public function remove(User $user, string $kind, string $id): void
    {
        if (str_starts_with($id, 'chip:')) {
            $this->rows($user, $kind)->firstWhere('id', (int) substr($id, 5))?->delete();

            return;
        }

        $key = str_starts_with($id, 'default:') ? substr($id, 8) : null;
        $default = $key !== null ? ($this->defaults($kind)[$key] ?? null) : null;

        if (! $default) {
            return;
        }

        DoctorChip::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'kind' => $kind,
                'default_key' => $key,
            ],
            [
                'label' => __($default['label']),
                'text_bn' => $default['text'],
                'is_primary' => $default['is_primary'],
                'hidden_at' => now(),
            ],
        );
    }

    /**
     * Put a hidden built-in back, with whatever the doctor had edited it to.
     */
    public function restore(User $user, string $kind, string $id): void
    {
        if (! str_starts_with($id, 'default:')) {
            return;
        }

        $this->rows($user, $kind)
            ->firstWhere('default_key', substr($id, 8))
            ?->update(['hidden_at' => null]);
    }

    /**
     * The ★ on the desk's Advice card: keep this line as a chip.
     *
     * Explicit, like saving a medicine default — never inferred from what the
     * doctor happened to prescribe. The line the patient reads becomes the
     * inserted text; the button gets a short label so the chip strip stays
     * readable.
     */
    public function saveAdviceLine(User $user, string $line): ?DoctorChip
    {
        $text = $this->clean($line, self::MAX_TEXT);

        if ($text === null) {
            return null;
        }

        $existing = $this->rows($user, DoctorChip::KIND_ADVICE)
            ->first(fn (DoctorChip $chip) => $chip->insertedText() === $text);

        if ($existing) {
            $existing->update(['hidden_at' => null]);

            return $existing;
        }

        return DoctorChip::query()->create([
            'user_id' => $user->id,
            'kind' => DoctorChip::KIND_ADVICE,
            'default_key' => null,
            'label' => $this->shorten($text),
            'text_bn' => $text,
            'is_primary' => true,
        ]);
    }

    /**
     * @return Collection<int, DoctorChip>
     */
    private function rows(User $user, string $kind): Collection
    {
        return DoctorChip::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->get();
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function shorten(string $text): string
    {
        return mb_strlen($text) > 28
            ? mb_substr($text, 0, 27).'…'
            : $text;
    }
}
