<?php

namespace App\Filament\TenantAdmin\Resources\ScheduleSessions\Schemas;

use App\Models\ScheduleSession;
use App\Support\StaffDeskScope;
use App\Support\DayOfWeek;
use App\Support\ScheduleSessionPace;
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
                    ->relationship(
                        'chamber',
                        'name',
                        modifyQueryUsing: fn ($query) => auth()->user() instanceof \App\Models\User
                            ? StaffDeskScope::constrainChambers($query, auth()->user())
                            : $query,
                    )
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('doctor_id')
                    ->relationship(
                        'doctor',
                        'name',
                        modifyQueryUsing: function ($query): void {
                            $user = auth()->user();
                            if (! $user instanceof \App\Models\User) {
                                return;
                            }

                            $doctorIds = StaffDeskScope::doctorIdsFor($user);
                            if ($doctorIds !== null) {
                                $query->whereIn('id', $doctorIds);
                            }
                        },
                    )
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('day_of_week')
                    ->required()
                    ->options(DayOfWeek::options()),
                TextInput::make('session_name')
                    ->required()
                    ->maxLength(255),
                Select::make('kind')
                    ->label(__('Room type'))
                    ->options(ScheduleSession::kindOptions())
                    ->native(false)
                    ->visible(fn (): bool => tenant()?->hasStations() ?? false)
                    ->required(fn (): bool => tenant()?->hasStations() ?? false)
                    ->helperText(__('Counseling and leftover Consult rows are free — Collect fee stays hidden. Visit and intervention use the fee catalogue.')),
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
                    ->helperText(function (Get $get): string {
                        $minutes = self::minutesEachFromState($get);

                        if ($minutes === null) {
                            return __('At least 1 patient per session.');
                        }

                        if ($minutes < ScheduleSessionPace::TIGHT_MINUTES_WARNING) {
                            return __('Only :minutes min each — too short.', [
                                'minutes' => $minutes,
                            ]);
                        }

                        return __('About :minutes min each — real consult?', [
                            'minutes' => $minutes,
                        ]);
                    }),
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
