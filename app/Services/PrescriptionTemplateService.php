<?php

namespace App\Services;

use App\Models\Condition;
use App\Models\PrescriptionTemplate;
use App\Models\User;
use App\Support\PrescriptionTiming;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Save and read a doctor's prescription packs.
 *
 * The pad hands this service the same shape it posts on save, so a pack is
 * literally "the prescription I just wrote, kept". Saving under an existing
 * name overwrites it — the alternative is a list of "IHD", "IHD 2", "IHD
 * final", which is how these features die.
 */
class PrescriptionTemplateService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(User $doctor, string $name, array $data): PrescriptionTemplate
    {
        $name = trim($name);

        return DB::transaction(function () use ($doctor, $name, $data): PrescriptionTemplate {
            $template = PrescriptionTemplate::query()->updateOrCreate(
                [
                    'tenant_id' => tenant('id'),
                    'user_id' => $doctor->id,
                    'name' => $name,
                ],
                [
                    'condition_id' => $this->resolveConditionId($data['diagnosis'] ?? null),
                    'advice' => $this->nullableString($data['advice'] ?? null),
                    'tests_advised' => $this->nullableString($data['tests_advised'] ?? null),
                    'follow_up_relative' => $this->nullableString($data['follow_up_relative'] ?? null),
                ]
            );

            $template->items()->delete();

            foreach (array_values($data['prescription_items'] ?? []) as $index => $item) {
                $medicine = $this->nullableString($item['medicine_name'] ?? null);

                if ($medicine === null) {
                    continue;
                }

                $template->items()->create([
                    'medicine_name' => $medicine,
                    'generic_name' => $this->nullableString($item['generic_name'] ?? null),
                    'dose' => $this->nullableString($item['dose'] ?? null),
                    'frequency' => $this->nullableString($item['frequency'] ?? null),
                    'duration' => $this->nullableString($item['duration'] ?? null),
                    'timing' => PrescriptionTiming::normalize(
                        is_string($item['timing'] ?? null) ? $item['timing'] : null
                    ),
                    'instructions' => $this->nullableString($item['instructions'] ?? null),
                    'sort_order' => $index,
                ]);
            }

            return $template->fresh('items');
        });
    }

    /**
     * Every pack this doctor owns, shaped for the Alpine pad.
     *
     * Loaded once with the page rather than fetched per diagnosis: a doctor
     * has tens of packs, not thousands, and a chamber's connection is the
     * thing most likely to fail mid-consult.
     *
     * @return list<array<string, mixed>>
     */
    public function forDoctor(User $doctor): array
    {
        return PrescriptionTemplate::query()
            ->where('user_id', $doctor->id)
            ->with('items')
            ->orderBy('name')
            ->get()
            ->map(fn (PrescriptionTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'condition_id' => $template->condition_id,
                'advice' => $template->advice,
                'tests_advised' => $template->tests_advised,
                'follow_up_relative' => $template->follow_up_relative,
                'items' => $template->items
                    ->map(fn ($item): array => [
                        'medicine_name' => $item->medicine_name,
                        'generic_name' => $item->generic_name,
                        'dose' => $item->dose,
                        'frequency' => $item->frequency,
                        'duration' => $item->duration,
                        'timing' => $item->timing,
                        'instructions' => $item->instructions,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function delete(User $doctor, string $templateId): void
    {
        PrescriptionTemplate::query()
            ->where('user_id', $doctor->id)
            ->whereKey($templateId)
            ->delete();
    }

    /**
     * The pad sends the diagnosis exactly as its picker holds it: a condition
     * id, or a free-text marker. Only a coded diagnosis can anchor a pack,
     * because free text has nothing stable to match on later.
     */
    private function resolveConditionId(?string $diagnosis): ?string
    {
        if (blank($diagnosis) || str_starts_with($diagnosis, '__free__:')) {
            return null;
        }

        return Condition::query()->whereKey($diagnosis)->value('id');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
