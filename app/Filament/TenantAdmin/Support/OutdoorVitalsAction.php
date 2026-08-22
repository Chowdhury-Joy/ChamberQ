<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\User;
use App\Services\VisitRecordService;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

final class OutdoorVitalsAction
{
    public static function make(Action $action, ?Closure $booking = null): Action
    {
        return $action
            ->label(__('Outdoor vitals'))
            ->icon('heroicon-o-heart')
            ->color('gray')
            ->modalDescription(__('Take every reading you can before the patient goes in. Leave a box blank only if you did not measure it.'))
            ->fillForm(function (Action $action, ...$args) use ($booking): array {
                $record = self::resolve($booking, [$action, ...$args])?->visitRecord;

                $state = [];
                foreach (VisitNotesFormSchema::VITAL_FIELDS as $field) {
                    $state[$field] = $record?->{$field};
                }

                return $state;
            })
            // The doctor's own boxes, not a hand-written subset: the desk takes
            // the whole O/E set, with the same range rules, so the doctor is
            // never asked to re-measure a pulse the desk already counted.
            ->schema(VisitNotesFormSchema::vitalsFields())
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
