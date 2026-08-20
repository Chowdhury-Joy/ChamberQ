<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Services\LiveSessionService;
use App\Services\RepeatBookingService;
use App\Services\StationsHandoffService;
use App\Services\VisitRecordService;
use App\Support\StaffDeskJobs;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

final class RosterRecordActions
{
    /**
     * @return list<Action|ActionGroup>
     */
    public static function compact(): array
    {
        return [
            ...self::slot(DeskActionLayout::SLOT_PRIMARY),
            ActionGroup::make(self::slot(DeskActionLayout::SLOT_MORE))
                ->label(__('More'))
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * @return list<Action>
     */
    private static function slot(string $slot): array
    {
        $name = fn (string $key): string => DeskActionLayout::actionName($key, $slot);
        $shown = fn (string $key): \Closure => fn (Booking $record): bool => DeskActionLayout::shows(
            $record,
            $key,
            $slot,
            DeskActionLayout::SURFACE_ROSTER,
        );

        return [
            PatientContinuityActions::toggleSeenBeforeSoftware(
                Action::make($name(DeskActionLayout::KEY_FOLLOW_UP)),
            )->visible(fn (Booking $record): bool => $record->patient !== null
                && (auth()->user()?->canWorkDesk() || auth()->user()?->isAdmin())
                && $shown(DeskActionLayout::KEY_FOLLOW_UP)($record)),

            VisitPaperScanForm::scanAction(
                Action::make($name(DeskActionLayout::KEY_SCAN)),
            )->visible(fn (Booking $record): bool => VisitPaperScanForm::mayScan($record)
                && $shown(DeskActionLayout::KEY_SCAN)($record)),

            Action::make($name(DeskActionLayout::KEY_CALL))
                ->label('Call to Chamber')
                ->color('primary')
                ->visible(fn (Booking $record): bool => DeskActionLayout::canCallOnRoster($record)
                    && $shown(DeskActionLayout::KEY_CALL)($record))
                ->action(function (Booking $record): void {
                    if (app(LiveSessionService::class)->bringBookingToChamber($record)) {
                        return;
                    }

                    Notification::make()
                        ->warning()
                        ->title(__('Someone is still with the doctor'))
                        ->body(__('Complete the patient currently in the chamber before calling #:serial in.', [
                            'serial' => $record->serial_number,
                        ]))
                        ->send();
                }),

            Action::make($name(DeskActionLayout::KEY_ARRIVED))
                ->label('Arrived')
                ->color('info')
                ->visible(fn (Booking $record): bool => DeskActionLayout::canArrived($record)
                    && $shown(DeskActionLayout::KEY_ARRIVED)($record))
                ->action(function (Booking $record): void {
                    $record->update(['status' => 'in_chamber']);

                    Notification::make()
                        ->title(__('Marked arrived'))
                        ->success()
                        ->send();
                }),

            Action::make($name(DeskActionLayout::KEY_NO_SHOW))
                ->label('No-show')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Booking $record): bool => ! (tenant()?->hasLiveQueue() ?? true)
                    && (auth()->user() instanceof \App\Models\User
                        && (StaffDeskJobs::canRunQueue(auth()->user())
                            || (! (tenant()?->hasLiveQueue() ?? true) && auth()->user()->canManageQueue())))
                    && in_array($record->status, ['waiting', 'in_chamber', 'called'], true)
                    && $shown(DeskActionLayout::KEY_NO_SHOW)($record))
                ->action(function (Booking $record): void {
                    $record->update(['status' => 'no_show']);

                    Notification::make()
                        ->title(__('Marked no-show'))
                        ->success()
                        ->send();
                }),

            VisitNotesFormSchema::configureModal(Action::make($name(DeskActionLayout::KEY_COMPLETE)))
                ->label('Mark Completed')
                ->color('success')
                ->visible(fn (Booking $record): bool => DeskActionLayout::canComplete($record)
                    && $shown(DeskActionLayout::KEY_COMPLETE)($record))
                ->form(fn (Booking $record): array => auth()->user()?->canRecordVisitNotes()
                    ? VisitNotesFormSchema::components(
                        $record->patient,
                        app(VisitRecordService::class)->lastRecordedVisitForPatient($record->patient, $record->id),
                        $record,
                    )
                    : [])
                ->modalHeading(fn (): ?string => auth()->user()?->canRecordVisitNotes()
                    ? __('Complete visit')
                    : null)
                ->modalDescription(fn (): ?string => auth()->user()?->canRecordVisitNotes()
                    ? __('Add optional notes, or leave everything blank and tap Complete.')
                    : null)
                ->modalSubmitActionLabel('Complete')
                ->action(function (
                    Booking $record,
                    array $data,
                    LiveSessionService $liveSessionService,
                    VisitRecordService $visitRecordService,
                ): void {
                    CompleteBookingWithVisitNotes::finish(
                        $record,
                        $data,
                        $liveSessionService,
                        $visitRecordService,
                    );
                }),

            VisitNotesFormSchema::configureModal(Action::make($name(DeskActionLayout::KEY_ENTER_RX)))
                ->label(fn (Booking $record): string => $record->visitRecord?->prescription
                    ? 'Edit prescription'
                    : 'Enter prescription')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (Booking $record): bool => DeskActionLayout::canEnterPrescription($record)
                    && $shown(DeskActionLayout::KEY_ENTER_RX)($record))
                ->modalHeading('Enter paper prescription')
                ->modalDescription(__('Copy in what the doctor wrote by hand. This does not change the visit status.'))
                ->modalSubmitActionLabel('Save prescription')
                ->fillForm(fn (Booking $record): array => VisitNotesFormSchema::staffStateFromRecord($record->visitRecord))
                ->form(fn (Booking $record): array => VisitNotesFormSchema::staffPrescriptionComponents($record))
                ->action(function (Booking $record, array $data, VisitRecordService $visitRecordService): void {
                    /** @var \App\Models\User $user */
                    $user = auth()->user();

                    $visitRecordService->saveStaffEnteredPrescription($record, $user, $data);

                    Notification::make()
                        ->title(__('Prescription saved'))
                        ->success()
                        ->send();
                }),

            OutdoorVitalsAction::make(Action::make($name(DeskActionLayout::KEY_VITALS)))
                ->visible(fn (Booking $record): bool => DeskActionLayout::canRecordVitals($record)
                    && $shown(DeskActionLayout::KEY_VITALS)($record)),

            StationsHandoffForm::bookAction(
                Action::make($name(DeskActionLayout::KEY_BOOK_INTERVENTION)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canSendVisit($record)
                    && $shown(DeskActionLayout::KEY_BOOK_INTERVENTION)($record);
            }),

            StationsHandoffForm::sendToLabAction(
                Action::make($name(DeskActionLayout::KEY_SEND_MSK)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canSendToLab($record)
                    && $shown(DeskActionLayout::KEY_SEND_MSK)($record);
            }),

            StationsHandoffForm::sendToReportAction(
                Action::make($name(DeskActionLayout::KEY_SEND_REPORT)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canSendToReport($record)
                    && $shown(DeskActionLayout::KEY_SEND_REPORT)($record);
            }),

            StationsHandoffForm::moveAction(
                Action::make($name(DeskActionLayout::KEY_MOVE_INTERVENTION)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canMove($record)
                    && $shown(DeskActionLayout::KEY_MOVE_INTERVENTION)($record);
            }),

            Action::make($name(DeskActionLayout::KEY_PREPPED))
                ->label(__('Mark prepped'))
                ->visible(fn (Booking $record): bool => DeskActionLayout::canAdvanceProcedure($record, DeskActionLayout::KEY_PREPPED)
                    && $shown(DeskActionLayout::KEY_PREPPED)($record))
                ->action(fn (Booking $record, StationsHandoffService $handoff) => self::advanceProcedure($record, $handoff, Booking::PROCEDURE_PREPPED)),

            Action::make($name(DeskActionLayout::KEY_CALL_DOCTOR))
                ->label(__('Call doctor'))
                ->color('warning')
                ->visible(fn (Booking $record): bool => DeskActionLayout::canAdvanceProcedure($record, DeskActionLayout::KEY_CALL_DOCTOR)
                    && $shown(DeskActionLayout::KEY_CALL_DOCTOR)($record))
                ->action(fn (Booking $record, StationsHandoffService $handoff) => self::advanceProcedure($record, $handoff, Booking::PROCEDURE_DOCTOR_CALLED)),

            Action::make($name(DeskActionLayout::KEY_PROCEDURE_DONE))
                ->label(__('Procedure done'))
                ->color('success')
                ->visible(fn (Booking $record): bool => DeskActionLayout::canAdvanceProcedure($record, DeskActionLayout::KEY_PROCEDURE_DONE)
                    && $shown(DeskActionLayout::KEY_PROCEDURE_DONE)($record))
                ->action(fn (Booking $record, StationsHandoffService $handoff) => self::advanceProcedure($record, $handoff, Booking::PROCEDURE_DONE)),

            StationsHandoffForm::sendToCounselingAction(
                Action::make($name(DeskActionLayout::KEY_COUNSELING)),
            )->visible(function (Booking $record) use ($shown): bool {
                return StationsHandoffForm::actorMaySend()
                    && app(StationsHandoffService::class)->canSendToCounseling($record)
                    && $shown(DeskActionLayout::KEY_COUNSELING)($record);
            }),

            CollectFeeAction::make(Action::make($name(DeskActionLayout::KEY_COLLECT_FEE)))
                ->visible(fn (Booking $record): bool => DeskActionLayout::canCollectFee($record)
                    && $shown(DeskActionLayout::KEY_COLLECT_FEE)($record)),

            AskReviewAction::whatsapp(Action::make($name(DeskActionLayout::KEY_REVIEW_WHATSAPP)))
                ->visible(fn (Booking $record): bool => AskReviewAction::canWhatsapp($record)
                    && $shown(DeskActionLayout::KEY_REVIEW_WHATSAPP)($record)),

            AskReviewAction::sms(Action::make($name(DeskActionLayout::KEY_REVIEW_SMS)))
                ->visible(fn (Booking $record): bool => AskReviewAction::canSms($record)
                    && $shown(DeskActionLayout::KEY_REVIEW_SMS)($record)),

            ConfirmSerialNotifyAction::whatsapp(Action::make($name(DeskActionLayout::KEY_SERIAL_WHATSAPP)))
                ->visible(fn (Booking $record): bool => ConfirmSerialNotifyAction::canWhatsapp($record)
                    && $shown(DeskActionLayout::KEY_SERIAL_WHATSAPP)($record)),

            ConfirmSerialNotifyAction::sms(Action::make($name(DeskActionLayout::KEY_SERIAL_SMS)))
                ->visible(fn (Booking $record): bool => ConfirmSerialNotifyAction::canSms($record)
                    && $shown(DeskActionLayout::KEY_SERIAL_SMS)($record)),

            Action::make($name(DeskActionLayout::KEY_REPEAT))
                ->label('Repeat sitting')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (Booking $record): bool => ! in_array($record->status, ['cancelled', 'no_show'], true)
                    && app(RepeatBookingService::class)->doctorAllowsRepeat($record)
                    && $shown(DeskActionLayout::KEY_REPEAT)($record))
                ->form(function (Booking $record): array {
                    return [
                        Select::make('weeks')
                            ->label(__('How many more sittings?'))
                            ->options(array_combine(
                                range(1, RepeatBookingService::MAX_WEEKS),
                                range(1, RepeatBookingService::MAX_WEEKS),
                            ))
                            ->default(4)
                            ->required()
                            ->live()
                            ->native(false),
                        Placeholder::make('dates_preview')
                            ->label(__('Dates that will get a serial'))
                            ->content(function (Get $get) use ($record): string {
                                $weeks = (int) ($get('weeks') ?: 4);
                                try {
                                    $plan = app(RepeatBookingService::class)->planDates($record, $weeks);
                                } catch (\InvalidArgumentException $e) {
                                    return $e->getMessage();
                                }

                                if ($plan['dates'] === []) {
                                    return __('No open sittings in the next weeks.');
                                }

                                $lines = array_map(
                                    fn (string $date): string => Carbon::parse($date)->toFormattedDateString(),
                                    $plan['dates'],
                                );
                                $skipNote = $plan['skipped'] === []
                                    ? ''
                                    : ' '.__('(:count dates skipped — full or closed)', [
                                        'count' => count($plan['skipped']),
                                    ]);

                                return implode(', ', $lines).$skipNote;
                            }),
                    ];
                })
                ->action(function (Booking $record, array $data): void {
                    try {
                        $result = app(RepeatBookingService::class)->repeatFromBooking(
                            $record,
                            (int) $data['weeks'],
                        );
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('Booked :count more sittings', ['count' => count($result['created'])]))
                        ->body($result['skipped'] === []
                            ? null
                            : __('Skipped :count dates that were full or closed.', [
                                'count' => count($result['skipped']),
                            ]))
                        ->success()
                        ->send();
                }),

            Action::make($name(DeskActionLayout::KEY_CANCEL_REPEAT))
                ->label('Cancel later sittings')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel later sittings')
                ->modalDescription(__('This visit stays. Later dates in this repeating series will be cancelled.'))
                ->visible(fn (Booking $record): bool => filled($record->repeat_series_id)
                    && Booking::query()
                        ->where('repeat_series_id', $record->repeat_series_id)
                        ->where('id', '!=', $record->id)
                        ->where('booking_date', '>', $record->booking_date->toDateString())
                        ->whereIn('status', ['waiting', 'called', 'skipped'])
                        ->exists()
                    && $shown(DeskActionLayout::KEY_CANCEL_REPEAT)($record))
                ->action(function (Booking $record): void {
                    $count = app(RepeatBookingService::class)->cancelRemainder($record);

                    Notification::make()
                        ->title(__('Cancelled :count later sittings', ['count' => $count]))
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function advanceProcedure(
        Booking $booking,
        StationsHandoffService $handoff,
        string $status,
    ): void {
        try {
            $handoff->advanceProcedureStatus($booking, $status);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(Booking::procedureStatusOptions()[$status] ?? __('Updated'))
            ->success()
            ->send();
    }
}
