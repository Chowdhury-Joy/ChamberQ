<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Services\LiveSessionService;
use App\Services\VisitRecordService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CompleteBookingWithVisitNotes
{
    public static function makeTableAction(string $name = 'complete'): Action
    {
        return Action::make($name)
            ->label('Mark Completed')
            ->color('success')
            ->action(function (Booking $record, array $data, LiveSessionService $liveSessionService, VisitRecordService $visitRecordService): void {
                static::finish($record, $data, $liveSessionService, $visitRecordService);
            });
    }

    public static function applyDoctorModal(Action $action): Action
    {
        return VisitNotesFormSchema::configureModal($action)
            ->modalHeading('Complete visit')
            ->modalDescription(__('Add optional notes, or leave everything blank and tap Complete.'))
            ->schema(VisitNotesFormSchema::components())
            ->modalSubmitActionLabel('Complete');
    }

    public static function applyStaffDirectComplete(Action $action): Action
    {
        return $action->action(function (Booking $record, LiveSessionService $liveSessionService): void {
            static::finish($record, [], $liveSessionService, app(VisitRecordService::class));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function finish(
        Booking $record,
        array $data,
        LiveSessionService $liveSessionService,
        VisitRecordService $visitRecordService,
    ): void {
        $user = auth()->user();

        if ($user?->canRecordVisitNotes() && $visitRecordService->submissionHasContent($data)) {
            $visitRecordService->saveForCompletedBooking($record, $user, $data);
        }

        $liveSessionService->completeBooking($record);

        Notification::make()
            ->title(__('Visit completed'))
            ->success()
            ->send();
    }

    /**
     * Save the consult's notes and close the visit, leaving the patient as the
     * session's current booking so the doctor can print or send the
     * prescription before calling the next patient in.
     *
     * @param  array<string, mixed>  $data
     */
    public static function completeCurrentSessionPatientWithoutAdvancing(
        array $data,
        LiveSessionService $liveSessionService,
        VisitRecordService $visitRecordService,
        ?\App\Models\LiveSession $session,
    ): void {
        if (! $session?->currentBooking) {
            return;
        }

        $booking = $session->currentBooking;
        $user = auth()->user();

        if ($user?->canRecordVisitNotes() && $visitRecordService->submissionHasContent($data)) {
            $visitRecordService->saveForCompletedBooking($booking, $user, $data);
        }

        $liveSessionService->completeCurrentPatientWithoutAdvancing($session);

        Notification::make()
            ->title(__('Visit completed'))
            ->body(__('Print or send the prescription, then tap Call next patient when ready.'))
            ->success()
            ->send();
    }
}
