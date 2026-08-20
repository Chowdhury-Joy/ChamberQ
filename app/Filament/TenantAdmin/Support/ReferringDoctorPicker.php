<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\ReferringDoctor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Shared "Referred by (outside GP)" dropdown for Collect fee and walk-in.
 */
final class ReferringDoctorPicker
{
    public static function select(): Select
    {
        return Select::make('referring_doctor_id')
            ->label(__('Referred by (outside GP)'))
            ->options(fn (): array => ReferringDoctor::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (ReferringDoctor $doctor) => [$doctor->id => $doctor->displayLabel()])
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => filled($value)
                ? ReferringDoctor::query()->find($value)?->displayLabel()
                : null)
            ->placeholder(__('Walk-in / no referrer'))
            ->helperText(__('Not in the list? Tap + to add them.'))
            ->searchable()
            ->native(false)
            ->createOptionForm([
                TextInput::make('name')
                    ->label(__('Doctor name'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('e.g. Dr Rashed')),
                TextInput::make('phone')
                    ->label(__('Phone (optional)'))
                    ->tel()
                    ->maxLength(20),
                TextInput::make('specialty')
                    ->label(__('Specialty / clinic (optional)'))
                    ->maxLength(255),
            ])
            ->createOptionModalHeading(__('Referring doctor not in list'))
            ->createOptionUsing(fn (array $data): int => (int) ReferringDoctor::findOrCreateFromDesk($data)->getKey())
            ->visible(fn (): bool => tenant()?->hasReferrals() ?? false);
    }
}
