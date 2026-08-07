<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Doctor;
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
            Select::make('medicine_name')
                ->label(__('Medicine'))
                ->placeholder(__('Choose from the list…'))
                ->options(fn (): array => app(MedicineService::class)->groupedSelectOptions(
                    auth()->user(),
                    $prescribingDoctor,
                ))
                ->searchable()
                ->live()
                ->required()
                ->native(false),
            TextInput::make('medicine_name_custom')
                ->label(__('Medicine name'))
                ->placeholder(__('Type medicine name'))
                ->maxLength(120)
                ->visible(fn (Get $get): bool => $get('medicine_name') === MedicineService::CUSTOM_MEDICINE_VALUE)
                ->required(fn (Get $get): bool => $get('medicine_name') === MedicineService::CUSTOM_MEDICINE_VALUE)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, Set $set): mixed => filled($state)
                    ? $set('medicine_name_custom', mb_strtoupper(trim($state)))
                    : null),
        ];
    }
}
