<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Exceptions\BookingUnavailableException;
use App\Filament\TenantAdmin\Support\StaffBookingForm;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Support\TenancyUrl;
use Carbon\Carbon;
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

    /**
     * Last successful booking, shown in the confirmation modal.
     *
     * @var array{serial: int, name: string, phone: string, date: string, sitting: string, whatsapp: string, ticket: string}|null
     */
    public ?array $lastBooked = null;

    public bool $showBookedSerialModal = false;

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
            'visit_type' => StaffBookingForm::TYPE_USUAL,
            'different_whatsapp' => false,
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

        try {
            $booking = StaffBookingForm::createFromState(
                $data,
                Carbon::parse($data['booking_date'])->toDateString(),
                allowOverflow: false,
                allowEndedToday: false,
                sendSms: true,
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

        $doctor = Doctor::resolveForBooking($booking);

        $this->lastBooked = [
            'serial' => (int) $booking->serial_number,
            'name' => (string) $booking->patient_name,
            'phone' => (string) $booking->patient_phone,
            'date' => $dateLabel,
            'sitting' => $this->sittingLabel($booking),
            'whatsapp' => ($doctor?->wantsWhatsapp(Doctor::NOTIFY_BOOKING_CONFIRMATION) ?? false)
                ? $booking->whatsappLink($waMessage)
                : null,
            'sms_url' => ($doctor?->wantsPushSms(Doctor::NOTIFY_BOOKING_CONFIRMATION) ?? false)
                ? tenant_web_route('bookings.sms.confirmation', $booking)
                : null,
            'ticket' => $ticketUrl,
            'auto_sms' => $doctor?->wantsAutoSms(Doctor::NOTIFY_BOOKING_CONFIRMATION) ?? false,
        ];

        $this->form->fill([
            'booking_date' => $data['booking_date'],
            'bookable' => $data['bookable'],
            'share_clinical_history' => true,
            'visit_type' => $data['visit_type'] ?? StaffBookingForm::TYPE_USUAL,
            'lab_type' => $data['lab_type'] ?? null,
            'intervention_type' => $data['intervention_type'] ?? null,
            'patient_phone' => null,
            'patient_name' => null,
            'patient_id' => null,
            'different_whatsapp' => false,
            'whatsapp_phone' => null,
            'nid' => null,
            'year_of_birth' => null,
            'referring_doctor_id' => null,
        ]);

        $this->showBookedSerialModal = true;
    }

    public function closeBookedSerialModal(): void
    {
        $this->showBookedSerialModal = false;
    }

    protected function sittingLabel(Booking $booking): string
    {
        $booking->loadMissing('bookable.doctor', 'feeCatalogItem');

        if ($booking->bookable instanceof LabCollectionSlot) {
            return __('Lab collection');
        }

        if ($booking->bookable instanceof ScheduleSession) {
            $session = $booking->bookable->session_name ?: __('Sitting');
            $doctor = $booking->bookable->doctor?->name;
            $procedure = $booking->feeCatalogItem?->label;
            $base = filled($doctor) ? $session.' · '.$doctor : $session;

            return filled($procedure) ? $base.' · '.$procedure : $base;
        }

        return __('Sitting');
    }
}
