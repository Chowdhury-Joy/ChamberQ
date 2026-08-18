<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\User;
use App\Models\VisitRecord;
use App\Support\StaffDeskScope;
use App\Services\FollowUpReminderService;
use Filament\Actions\Action;
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

        $query = VisitRecord::query()
            ->whereNotNull('follow_up_reminder_whatsapp_queued_at')
            ->whereNull('follow_up_reminder_whatsapp_sent_at')
            ->where('follow_up_date', '>=', now()->toDateString())
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
                    ->label(__('Confirm WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->action(function (VisitRecord $record): void {
                        $booking = $record->booking;

                        if (! $booking instanceof Booking) {
                            return;
                        }

                        $doctor = Doctor::resolveForBooking($booking);

                        if (! $doctor) {
                            return;
                        }

                        $url = $booking->whatsappLink(
                            app(FollowUpReminderService::class)->whatsappMessage($booking, $record, $doctor)
                        );

                        $record->forceFill(['follow_up_reminder_whatsapp_sent_at' => now()])->save();

                        $this->js('window.open('.json_encode($url).', "_blank")');
                    })
                    ->visible(fn (VisitRecord $record): bool => filled($record->booking?->patient_phone)),
            ])
            ->emptyStateHeading(__('No follow-up WhatsApp reminders waiting'))
            ->emptyStateDescription(__('When a doctor turns on follow-up WhatsApp, patients appear here 3 days before their follow-up date for staff to confirm.'));
    }
}
