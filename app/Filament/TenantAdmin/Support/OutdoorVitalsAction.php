<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\User;
use App\Services\VisitRecordService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

final class OutdoorVitalsAction
{
    public static function make(Action $action, ?Closure $booking = null): Action
    {
        return $action
            ->label(__('Outdoor vitals'))
            ->icon('heroicon-o-heart')
            ->color('gray')
            ->fillForm(function (Action $action, ...$args) use ($booking): array {
                $record = self::resolve($booking, [$action, ...$args]);

                return [
                    'weight_kg' => $record?->visitRecord?->weight_kg,
                    'bp_systolic' => $record?->visitRecord?->bp_systolic,
                    'bp_diastolic' => $record?->visitRecord?->bp_diastolic,
                ];
            })
            ->schema([
                TextInput::make('weight_kg')
                    ->label(__('Weight (kg)'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('bp_systolic')
                    ->label(__('BP systolic'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('bp_diastolic')
                    ->label(__('BP diastolic'))
                    ->numeric()
                    ->minValue(0),
            ])
            ->action(function (array $data, Action $action, ...$args) use ($booking): void {
                $record = self::resolve($booking, [$action, ...$args]);
                if (! $record) {
                    return;
                }

                /** @var User $user */
                $user = auth()->user();

                try {
                    app(VisitRecordService::class)->saveStaffVitals($record, $user, $data);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Vitals saved'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  list<mixed>  $args
     */
    private static function resolve(?Closure $booking, array $args): ?Booking
    {
        if ($booking) {
            $resolved = $booking();

            return $resolved instanceof Booking ? $resolved : null;
        }

        foreach ($args as $arg) {
            if ($arg instanceof Booking) {
                return $arg;
            }

            if ($arg instanceof Action) {
                $mounted = $arg->getRecord();
                if ($mounted instanceof Booking) {
                    return $mounted;
                }
            }
        }

        return null;
    }
}
