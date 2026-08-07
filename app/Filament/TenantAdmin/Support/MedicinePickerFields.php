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
        return self::medicineSelect(__('Medicine (brand)'), $prescribingDoctor)
            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                if (blank($state)) {
                    return;
                }

                $set('medicine_name', mb_strtoupper(trim($state)));

                $match = Medicine::query()
                    ->where('brand_name', mb_strtoupper(trim($state)))
                    ->first();

                if (! $match) {
                    return;
                }

                if (blank($get('generic_name'))) {
                    $set('generic_name', $match->generic_name);
                }
                if (blank($get('dose'))) {
                    $prefillDose = $match->default_strength;
                    if (filled($prefillDose) && in_array($prefillDose, VisitNotesFormSchema::DOSE_PRESETS, true)) {
                        $set('dose', $prefillDose);
                        $set('dose_other', null);
                    } elseif (filled($prefillDose)) {
                        $set('dose', 'other');
                        $set('dose_other', $prefillDose);
                    }
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

    private static function medicineSelect(string $label, ?Doctor $prescribingDoctor = null): Select
    {
        $medicineService = app(MedicineService::class);

        return Select::make('medicine_name')
            ->label($label)
            ->placeholder(__('Choose from the list…'))
            ->options(fn (): array => $medicineService->groupedSelectOptions(
                auth()->user(),
                $prescribingDoctor,
            ))
            ->searchable()
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
                $match = Medicine::query()->where('brand_name', $brand)->first();

                return $match?->displayLabel() ?? $brand;
            });
    }
}
