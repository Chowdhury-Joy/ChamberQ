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
            ->label(__('Mark Completed'))
            ->color('success')
            ->action(function (Booking $record, array $data, LiveSessionService $liveSessionService, VisitRecordService $visitRecordService): void {
                static::finish($record, $data, $liveSessionService, $visitRecordService);
            });
    }

    public static function applyDoctorModal(Action $action): Action
    {
        return $action
            ->modalHeading(__('Complete visit'))
            ->modalDescription(__('Add optional notes, or leave everything blank and tap Complete.'))
            ->schema(VisitNotesFormSchema::components())
            ->modalSubmitActionLabel(__('Complete'));
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

    public static function finishCurrentSessionPatient(
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

        $liveSessionService->completeCurrentPatient($session);

        Notification::make()
            ->title(__('Called next patient'))
            ->success()
            ->send();
    }
}
