<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Schemas;

use App\Models\ScheduleSession;
use App\Support\DayOfWeek;
use App\Support\ScheduleSessionPace;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ScheduleSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('chamber_id')
                    ->relationship('chamber', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('doctor_id')
                    ->relationship('doctor', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('day_of_week')
                    ->required()
                    ->options(DayOfWeek::options()),
                TextInput::make('session_name')
                    ->required()
                    ->maxLength(255),
                TimePicker::make('start_time')
                    ->required()
                    ->seconds(false)
                    ->live(),
                TimePicker::make('end_time')
                    ->required()
                    ->seconds(false)
                    ->live()
                    ->rule(function (Get $get) {
                        return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $start = $get('start_time');
                            if (blank($start) || blank($value)) {
                                return;
                            }
                            if ((string) $value <= (string) $start) {
                                $fail(__('End time must be after start time.'));
                            }
                        };
                    }),
                TextInput::make('slot_cap')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->live()
                    ->helperText(__('At least 1 patient per session.')),
                Placeholder::make('minutes_each_hint')
                    ->label('')
                    ->content(function (Get $get): string {
                        $minutes = self::minutesEachFromState($get);

                        if ($minutes === null) {
                            return '';
                        }

                        return __('At this window that is about :minutes minutes each. Does that match a real consult?', [
                            'minutes' => $minutes,
                        ]);
                    })
                    ->visible(fn (Get $get): bool => self::minutesEachFromState($get) !== null)
                    ->extraAttributes(fn (Get $get): array => ScheduleSessionPace::TIGHT_MINUTES_WARNING > (self::minutesEachFromState($get) ?? 99)
                        ? ['class' => 'text-warning-600 dark:text-warning-400 font-medium']
                        : ['class' => 'text-gray-600 dark:text-gray-400']),
                TextInput::make('walk_in_overflow_cap')
                    ->label(__('Extra walk-in seats'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText(__('Online stops at the published seat count. The desk can still take this many after that. Those people wait after the published list.')),
            ]);
    }

    private static function minutesEachFromState(Get $get): ?int
    {
        $start = $get('start_time');
        $end = $get('end_time');
        $cap = (int) $get('slot_cap');

        if (blank($start) || blank($end) || $cap < 1) {
            return null;
        }

        if ((string) $end <= (string) $start) {
            return null;
        }

        $session = new ScheduleSession([
            'start_time' => $start,
            'end_time' => $end,
            'slot_cap' => $cap,
        ]);

        return ScheduleSessionPace::minutesPerPatient($session);
    }
}
