<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Services\PatientService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Desk control for "this person was treated here before ChamberQ".
 *
 * Staff cannot edit the Patients resource; Daily Roster / Live Queue is
 * where they meet the returning paper-file patient.
 */
class PatientContinuityActions
{
    public static function toggleSeenBeforeSoftware(Action $action): Action
    {
        return $action
            ->label(fn (Booking $record): string => $record->patient?->seen_before_software
                ? __('Mark as first visit')
                : __('For follow up'))
            ->icon(fn (Booking $record): string => $record->patient?->seen_before_software
                ? 'heroicon-o-user'
                : 'heroicon-o-identification')
            ->color('gray')
            ->visible(fn (Booking $record): bool => $record->patient !== null
                && (auth()->user()?->canWorkDesk() || auth()->user()?->isAdmin()))
            ->action(function (Booking $record, PatientService $patientService): void {
                $patient = $record->patient;

                if (! $patient) {
                    return;
                }

                $nowMarked = ! $patient->seen_before_software;
                $patientService->setSeenBeforeSoftware($patient, $nowMarked);

                Notification::make()
                    ->title($nowMarked
                        ? __('Marked as seen here before ChamberQ')
                        : __('Marked as a first visit in ChamberQ'))
                    ->success()
                    ->send();
            });
    }
}
