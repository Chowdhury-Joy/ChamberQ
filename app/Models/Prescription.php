<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\TenancyUrl;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'visit_record_id',
        'patient_id',
        'prescribed_by',
        'advice',
        'follow_up_date',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
    ];

    public function visitRecord(): BelongsTo
    {
        return $this->belongsTo(VisitRecord::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('sort_order');
    }

    public function followUpLabel(): ?string
    {
        $record = $this->relationLoaded('visitRecord') ? $this->visitRecord : $this->visitRecord()->first();

        return \App\Filament\TenantAdmin\Support\VisitNotesFormSchema::followUpDisplayLabel(
            $this->follow_up_date ?? $record?->follow_up_date,
            $record?->follow_up_note,
        );
    }

    /**
     * How long a shared prescription link stays openable.
     *
     * Expiring, not single-use: a patient re-opening the same WhatsApp message
     * a day later to re-check a dose is the expected case, not an abuse.
     */
    public const SHARE_LINK_EXPIRY_HOURS = 48;

    /**
     * Unguessable, expiring link to this one prescription's patient-facing
     * view. Carries no login and reaches no other clinical data — see the
     * 2026-08-07 decision in `decisions.md`.
     */
    public function shareUrl(): string
    {
        return TenancyUrl::temporarySignedRoute(
            'prescriptions.share',
            now()->addHours(self::SHARE_LINK_EXPIRY_HOURS),
            ['prescription' => $this->id],
        );
    }

    /**
     * Doctor and chamber for this prescription, falling back to the practice's
     * first of each when the booking is not a schedule session. Shared by the
     * doctor-only print view and the patient share view so both name the same
     * prescriber.
     *
     * @return array{doctor: ?Doctor, chamber: ?Chamber}
     */
    public function resolveDoctorChamber(): array
    {
        $this->loadMissing('visitRecord.booking');

        $booking = $this->visitRecord?->booking;
        $doctor = null;
        $chamber = null;

        if ($booking && $booking->bookable_type === ScheduleSession::class) {
            $session = ScheduleSession::with(['doctor', 'chamber'])->find($booking->bookable_id);
            $doctor = $session?->doctor;
            $chamber = $session?->chamber;
        }

        return [
            'doctor' => $doctor ?? Doctor::query()->first(),
            'chamber' => $chamber ?? Chamber::query()->first(),
        ];
    }
}
