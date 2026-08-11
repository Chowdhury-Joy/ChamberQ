<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Doctor;
use App\Models\Medicine;
use App\Services\MedicineService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Shared medicine dropdown (grouped by category) for prescription and My medicines.
 */
class MedicinePickerFields
{
    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function schema(?Doctor $prescribingDoctor = null): array
    {
        return [
            self::medicineSelect(__('Medicine'), $prescribingDoctor),
        ];
    }

    public static function prescriptionMedicineSelect(?Doctor $prescribingDoctor = null): Select
    {
        return self::medicineSelect(__('Medicine (brand)'), $prescribingDoctor, excludeSiblings: true)
            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                if (blank($state)) {
                    return;
                }

                $set('medicine_name', mb_strtoupper(trim($state)));

                // A brand now has several SKUs. Order by tier so prefill takes
                // the hand-verified adult strength, never whichever row the
                // database happened to return first — which could be an IV
                // infusion of the same brand.
                $match = Medicine::query()
                    ->where('brand_name', mb_strtoupper(trim($state)))
                    ->orderBy('priority')
                    ->first();

                if (! $match) {
                    return;
                }

                if (blank($get('generic_name'))) {
                    $set('generic_name', $match->generic_name);
                }
                if (blank($get('dose'))) {
                    $prefill = VisitNotesFormSchema::prefillDoseFromStrength($match->default_strength);
                    $set('dose', $prefill['dose']);
                    $set('dose_other', $prefill['dose_other']);
                }
                if (blank($get('frequency'))) {
                    $set('frequency', '1+1+1');
                }
                if (blank($get('duration'))) {
                    $set('duration', '5 days');
                }

                $set('_prefilled', true);
            });
    }

    private static function medicineSelect(
        string $label,
        ?Doctor $prescribingDoctor = null,
        bool $excludeSiblings = false,
    ): Select {
        $medicineService = app(MedicineService::class);

        return Select::make('medicine_name')
            ->label($label)
            ->placeholder(__('Type a brand or generic…'))
            // Search-driven, not a static option list. The catalogue is 24,491
            // SKUs; serialising it into Choices.js once per repeater row — which
            // `options()` did — is megabytes of DOM per medicine and a full
            // catalogue rebuild on every `live()` round trip. `search()` caps at
            // MAX_RESULTS and ranks by tier, so what comes back is short and the
            // doctor's own brands lead.
            ->getSearchResultsUsing(function (string $search, Get $get) use ($medicineService, $prescribingDoctor, $excludeSiblings): array {
                $exclude = $excludeSiblings
                    ? self::brandsSelectedOnOtherRepeaterRows($get)
                    : [];

                return $medicineService
                    ->search($search, auth()->user(), $prescribingDoctor)
                    ->reject(fn (array $row): bool => in_array(
                        mb_strtoupper($row['brand_name']),
                        $exclude,
                        true,
                    ))
                    ->mapWithKeys(fn (array $row): array => [$row['brand_name'] => $row['label']])
                    ->all();
            })
            ->searchable()
            ->searchDebounce(250)
            ->live()
            ->required()
            ->native(false)
            ->createOptionForm([
                TextInput::make('brand_name')
                    ->label(__('Medicine name'))
                    ->required()
                    ->maxLength(120),
            ])
            ->createOptionModalHeading(__('Medicine not in list'))
            ->createOptionUsing(fn (array $data): string => $medicineService->normalizeMedicineName($data['brand_name']))
            ->getOptionLabelUsing(function (?string $value): ?string {
                if (blank($value)) {
                    return null;
                }

                $brand = mb_strtoupper(trim($value));
                $match = Medicine::query()
                    ->where('brand_name', $brand)
                    ->orderBy('priority')
                    ->first();

                return $match?->displayLabel() ?? $brand;
            });
    }

    /**
     * Brands already chosen on sibling repeater rows (current row kept visible).
     *
     * @return list<string>
     */
    private static function brandsSelectedOnOtherRepeaterRows(Get $get): array
    {
        $currentBrand = mb_strtoupper(trim((string) ($get('medicine_name') ?? '')));
        $exclude = [];

        foreach ($get('../../prescription_items') ?? [] as $item) {
            $brand = mb_strtoupper(trim((string) ($item['medicine_name'] ?? '')));

            if ($brand === '' || $brand === $currentBrand) {
                continue;
            }

            $exclude[] = $brand;
        }

        return array_values(array_unique($exclude));
    }
}
