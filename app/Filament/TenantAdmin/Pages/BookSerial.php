<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Exceptions\BookingUnavailableException;
use App\Filament\TenantAdmin\Support\StaffBookingForm;
use App\Models\Booking;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Services\BookingService;
use App\Services\CarePath;
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
        [$type, $id] = explode(':', (string) ($data['bookable'] ?? ''), 2);

        $bookable = $type === 'lab'
            ? LabCollectionSlot::findOrFail($id)
            : ScheduleSession::findOrFail($id);

        $visitType = (string) ($data['visit_type'] ?? StaffBookingForm::TYPE_USUAL);
        $labType = is_string($data['lab_type'] ?? null) ? $data['lab_type'] : null;
        $interventionTypeId = filled($data['intervention_type'] ?? null)
            ? (int) $data['intervention_type']
            : null;

        if ($visitType === StaffBookingForm::TYPE_INTERVENTION
            && StaffBookingForm::interventionTypeOptions() !== []
            && $interventionTypeId === null) {
            Notification::make()
                ->title(__('Pick an intervention type.'))
                ->danger()
                ->send();

            return;
        }

        if (! self::bookableMatchesVisitType($bookable, $visitType, $labType)) {
            Notification::make()
                ->title(__('That sitting does not match the visit type.'))
                ->danger()
                ->send();

            return;
        }

        $patientId = ($data['patient_id'] ?? null) === '__new__'
            ? null
            : ($data['patient_id'] ?? null);

        $labTestIds = [];
        if (is_string($labType) && str_starts_with($labType, 'test:')) {
            $labTestIds[] = (int) substr($labType, 5);
        }

        $forcedCarePath = match ($visitType) {
            StaffBookingForm::TYPE_FOLLOWUP => CarePath::FOLLOW_UP,
            StaffBookingForm::TYPE_USUAL => CarePath::VISIT,
            default => null,
        };

        try {
            $booking = app(BookingService::class)->createBookingForBookable(
                $bookable,
                Carbon::parse($data['booking_date'])->toDateString(),
                $data['patient_name'],
                $data['patient_phone'],
                $labTestIds,
                sendSms: true,
                patientId: $patientId,
                wantsEarlierDate: false,
                whatsappPhone: ! empty($data['different_whatsapp']) && filled($data['whatsapp_phone'] ?? null)
                    ? (string) $data['whatsapp_phone']
                    : null,
                shareClinicalHistory: array_key_exists('share_clinical_history', $data)
                    ? (bool) $data['share_clinical_history']
                    : true,
                nid: $data['nid'] ?? null,
                yearOfBirth: filled($data['year_of_birth'] ?? null) ? (int) $data['year_of_birth'] : null,
                allowOverflow: false,
                allowEndedToday: false,
                seenBeforeSoftware: $visitType === StaffBookingForm::TYPE_FOLLOWUP ? true : null,
                allowMskWalkIn: $bookable instanceof ScheduleSession
                    && $bookable->kind === ScheduleSession::KIND_MSK,
                referringDoctorId: filled($data['referring_doctor_id'] ?? null)
                    ? (int) $data['referring_doctor_id']
                    : null,
                forcedCarePath: $forcedCarePath,
                feeCatalogItemId: $visitType === StaffBookingForm::TYPE_INTERVENTION
                    ? $interventionTypeId
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

        $this->lastBooked = [
            'serial' => (int) $booking->serial_number,
            'name' => (string) $booking->patient_name,
            'phone' => (string) $booking->patient_phone,
            'date' => $dateLabel,
            'sitting' => $this->sittingLabel($booking),
            'whatsapp' => $booking->whatsappLink($waMessage),
            'ticket' => $ticketUrl,
        ];

        $this->form->fill([
            'booking_date' => $data['booking_date'],
            'bookable' => $data['bookable'],
            'share_clinical_history' => true,
            'visit_type' => $visitType,
            'lab_type' => $labType,
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

    private static function bookableMatchesVisitType(
        ScheduleSession|LabCollectionSlot $bookable,
        string $visitType,
        ?string $labType,
    ): bool {
        if ($bookable instanceof LabCollectionSlot) {
            return $visitType === StaffBookingForm::TYPE_LAB
                && $labType !== null
                && $labType !== StaffBookingForm::LAB_MSK;
        }

        return match ($visitType) {
            StaffBookingForm::TYPE_INTERVENTION => $bookable->kind === ScheduleSession::KIND_INTERVENTION,
            StaffBookingForm::TYPE_LAB => $bookable->kind === ScheduleSession::KIND_MSK
                && $labType === StaffBookingForm::LAB_MSK,
            default => $bookable->isPubliclyBookable(),
        };
    }
}
