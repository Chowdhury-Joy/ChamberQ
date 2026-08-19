<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Exceptions\BookingUnavailableException;
use App\Filament\TenantAdmin\Support\StaffBookingForm;
use App\Models\Booking;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Services\BookingService;
use App\Support\TenancyUrl;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Desk or call-centre booking for a chosen date — not only today’s walk-in.
 */
class BookSerial extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Book serial';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Book serial';

    protected string $view = 'filament.tenant-admin.pages.book-serial';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return (($user?->isAdmin() ?? false) || ($user?->canWorkDesk() ?? false))
            && (tenant()?->hasFrontDoor() ?? false);
    }

    public function mount(): void
    {
        $this->form->fill([
            'booking_date' => Carbon::today()->toDateString(),
            'share_clinical_history' => true,
            'seen_before_software' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(StaffBookingForm::components());
    }

    public function book(): void
    {
        $data = $this->form->getState();
        [$type, $id] = explode(':', (string) ($data['bookable'] ?? ''), 2);

        $bookable = $type === 'lab'
            ? LabCollectionSlot::findOrFail($id)
            : ScheduleSession::findOrFail($id);

        if ($bookable instanceof ScheduleSession && ! $bookable->isPubliclyBookable()) {
            Notification::make()
                ->title(__('That sitting is not bookable from this page.'))
                ->danger()
                ->send();

            return;
        }

        $patientId = ($data['patient_id'] ?? null) === '__new__'
            ? null
            : ($data['patient_id'] ?? null);

        try {
            $booking = app(BookingService::class)->createBookingForBookable(
                $bookable,
                Carbon::parse($data['booking_date'])->toDateString(),
                $data['patient_name'],
                $data['patient_phone'],
                [],
                sendSms: true,
                patientId: $patientId,
                wantsEarlierDate: false,
                whatsappPhone: null,
                shareClinicalHistory: array_key_exists('share_clinical_history', $data)
                    ? (bool) $data['share_clinical_history']
                    : true,
                nid: $data['nid'] ?? null,
                yearOfBirth: filled($data['year_of_birth'] ?? null) ? (int) $data['year_of_birth'] : null,
                allowOverflow: false,
                allowEndedToday: false,
                seenBeforeSoftware: ! empty($data['seen_before_software']) ? true : null,
                referringDoctorId: filled($data['referring_doctor_id'] ?? null)
                    ? (int) $data['referring_doctor_id']
                    : null,
            );
        } catch (BookingUnavailableException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $dateLabel = $booking->booking_date?->translatedFormat('j F Y') ?? '';
        $ticketUrl = TenancyUrl::publicAbsolute((string) $booking->tenant_id, '/bookings/'.$booking->id);
        $waMessage = __('Hello :name, your serial is :serial on :date.', [
            'name' => $booking->patient_name,
            'serial' => $booking->serial_number,
            'date' => $dateLabel,
        ]);

        Notification::make()
            ->title(__('Serial :n booked for :date', [
                'n' => $booking->serial_number,
                'date' => $dateLabel,
            ]))
            ->body(__('A confirmation SMS will go if the wallet has credit. You can also send WhatsApp or open the ticket.'))
            ->success()
            ->actions([
                Action::make('whatsapp')
                    ->label(__('WhatsApp'))
                    ->url($booking->whatsappLink($waMessage))
                    ->openUrlInNewTab(),
                Action::make('ticket')
                    ->label(__('Open ticket'))
                    ->url($ticketUrl)
                    ->openUrlInNewTab(),
            ])
            ->persistent()
            ->send();

        $this->form->fill([
            'booking_date' => $data['booking_date'],
            'bookable' => $data['bookable'],
            'share_clinical_history' => true,
            'seen_before_software' => false,
            'patient_phone' => null,
            'patient_name' => null,
            'patient_id' => null,
            'nid' => null,
            'year_of_birth' => null,
            'referring_doctor_id' => null,
        ]);
    }
}
