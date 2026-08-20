<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\VisitRecord;
use App\Support\StaffDeskScope;
use App\Services\FollowUpReminderService;
use App\Services\SmsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class FollowUpReminders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Follow-up reminders';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Follow-up reminders';

    protected string $view = 'filament.tenant-admin.pages.follow-up-reminders';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return ($user?->canWorkDesk() ?? false)
            && (tenant()?->hasPrescription() ?? false);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $ids = app(FollowUpReminderService::class)->pendingStaffTapVisitIds();

        $query = VisitRecord::query()
            ->whereIn('id', $ids === [] ? [0] : $ids)
            ->with(['booking'])
            ->orderBy('follow_up_date');

        if ($user instanceof User) {
            $query->whereHas('booking', fn ($bookingQuery) => StaffDeskScope::constrainBookings($bookingQuery, $user));
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('booking.patient_name')
                    ->label(__('Patient'))
                    ->searchable(),
                TextColumn::make('booking.patient_phone')
                    ->label(__('Phone')),
                TextColumn::make('follow_up_date')
                    ->label(__('Follow-up'))
                    ->date(),
                TextColumn::make('booking.bookable.doctor.name')
                    ->label(__('Doctor'))
                    ->default('—'),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label(__('Push WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->action(function (VisitRecord $record): void {
                        $booking = $record->booking;

                        if (! $booking instanceof Booking) {
                            return;
                        }

                        $doctor = Doctor::resolveForBooking($booking);

                        if (! $doctor?->wantsWhatsapp(Doctor::NOTIFY_FOLLOW_UP)) {
                            return;
                        }

                        $url = $booking->whatsappLink(
                            app(FollowUpReminderService::class)->whatsappMessage($booking, $record, $doctor)
                        );

                        $record->forceFill(['follow_up_reminder_whatsapp_sent_at' => now()])->save();

                        $this->js('window.open('.json_encode($url).', "_blank")');
                    })
                    ->visible(function (VisitRecord $record): bool {
                        $booking = $record->booking;
                        if (! $booking instanceof Booking || blank($booking->patient_phone)) {
                            return false;
                        }

                        return Doctor::resolveForBooking($booking)?->wantsWhatsapp(Doctor::NOTIFY_FOLLOW_UP) ?? false;
                    }),
                Action::make('sms')
                    ->label(__('Push SMS'))
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('warning')
                    ->action(function (VisitRecord $record): void {
                        $booking = $record->booking;
                        $doctor = $booking instanceof Booking ? Doctor::resolveForBooking($booking) : null;

                        if (! $booking instanceof Booking || ! $doctor) {
                            return;
                        }

                        $message = app(SmsService::class)->sendFollowUpReminder(
                            $booking,
                            $record,
                            $doctor,
                            staffTap: true,
                        );

                        if ($message?->status === SmsMessage::STATUS_SENT) {
                            $record->forceFill(['follow_up_reminder_sms_sent_at' => now()])->save();
                            Notification::make()->title(__('Follow-up SMS sent'))->success()->send();

                            return;
                        }

                        $error = match ($message?->status) {
                            SmsMessage::STATUS_SKIPPED_NO_BALANCE => __('No SMS credits left'),
                            SmsMessage::STATUS_SKIPPED_PREF_OFF => __('SMS is off for this doctor'),
                            SmsMessage::STATUS_SKIPPED_DISABLED => __('SMS is disabled'),
                            default => __('Could not send SMS'),
                        };

                        Notification::make()->title($error)->danger()->send();
                    })
                    ->visible(function (VisitRecord $record): bool {
                        if ($record->follow_up_reminder_sms_sent_at !== null) {
                            return false;
                        }

                        $booking = $record->booking;
                        if (! $booking instanceof Booking || blank($booking->patient_phone)) {
                            return false;
                        }

                        return Doctor::resolveForBooking($booking)?->wantsPushSms(Doctor::NOTIFY_FOLLOW_UP) ?? false;
                    }),
            ])
            ->emptyStateHeading(__('No follow-up reminders waiting'))
            ->emptyStateDescription(__('When a doctor turns on follow-up Push WhatsApp or Push SMS, patients appear here 3 days before their follow-up date for staff to confirm.'));
    }
}
