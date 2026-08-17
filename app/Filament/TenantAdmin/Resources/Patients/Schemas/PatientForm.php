<?php

namespace App\Filament\TenantAdmin\Resources\Patients\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('phone')
                ->tel()
                ->required()
                ->maxLength(20)
                ->rule('regex:/^01[3-9]\d{8}$/'),
            TextInput::make('nid')
                ->label(__('NID number (optional)'))
                ->helperText(__('National ID — not shown on tickets or SMS. Used to reconnect records when the phone number changes.'))
                ->maxLength(17)
                ->dehydrateStateUsing(fn (?string $state): ?string => \App\Support\BdNid::normalize($state))
                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! \App\Support\BdNid::isValid((string) $value)) {
                        $fail(__('Please enter a valid NID (10 or 13 digits), or leave it blank.'));
                    }
                })
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule) => $rule
                        ->where('tenant_id', tenant('id'))
                        ->whereNotNull('nid'),
                ),
            DatePicker::make('date_of_birth')
                ->label(__('Date of birth'))
                ->maxDate(now()),
            TextInput::make('age')
                ->numeric()
                ->minValue(0)
                ->maxValue(120),
            DatePicker::make('age_recorded_at')
                ->label(__('Age recorded on'))
                ->maxDate(now()),
            Select::make('sex')
                ->options([
                    'male' => __('Male'),
                    'female' => __('Female'),
                    'other' => __('Other'),
                ]),
            Textarea::make('allergies')
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('conditions')
                ->label(__('Ongoing conditions'))
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('medicines')
                ->label(__('Current medicines'))
                ->rows(2)
                ->columnSpanFull(),
            Toggle::make('seen_before_software')
                ->label(__('Seen here before ChamberQ'))
                ->helperText(__('Tick if they were treated on paper before this software. The consult screen will show them as a returning patient, not a first visit. Staff can also set this from today’s list.'))
                ->columnSpanFull(),
        ]);
    }
}
