<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasUuids, BelongsToTenant;

    /**
     * How long a finished visit may sit without the next patient being called
     * before the consult screens say so. Short on purpose: the room is empty
     * and someone is waiting outside.
     */
    public const CALL_NEXT_NUDGE_SECONDS = 30;

    public const PROCEDURE_LOGGED = 'logged';

    public const PROCEDURE_PREPPED = 'prepped';

    public const PROCEDURE_DOCTOR_CALLED = 'doctor_called';

    public const PROCEDURE_DONE = 'done';

    protected $fillable = [
        'bookable_type',
        'bookable_id',
        'booking_date',
        'patient_id',
        'repeat_series_id',
        'patient_name',
        'patient_phone',
        'whatsapp_phone',
        'serial_number',
        'voucher_number',
        'related_booking_id',
        'procedure_status',
        'referring_doctor_id',
        'is_overflow',
        'status',
        'wants_earlier_date',
        'cancelled_at',
        'cancellation_reason',
        'patient_notified',
        'called_at',
        'in_chamber_at',
        'completed_at',
        'skip_count',
        'retry_queue_position',
    ];

    protected $casts = [
        'booking_date' => DateOnly::class,
        'serial_number' => 'integer',
        'voucher_number' => 'integer',
        'is_overflow' => 'boolean',
        'wants_earlier_date' => 'boolean',
        'cancelled_at' => 'datetime',
        'patient_notified' => 'boolean',
        'called_at' => 'datetime',
        'in_chamber_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * A `wa.me` deep link staff can tap to message this patient.
     *
     * v1 has no WhatsApp API integration by design — this is a link a human
     * presses, not an automated send.
     */
    public function whatsappLink(?string $message = null): string
    {
        // Prefer an explicit WhatsApp number when the patient said their WA
        // is different from the phone they show at reception.
        $raw = filled($this->whatsapp_phone) ? $this->whatsapp_phone : $this->patient_phone;
        // wa.me needs a bare international number: 8801XXXXXXXXX.
        $digits = preg_replace('/\D/', '', (string) $raw);

        if (str_starts_with($digits, '0')) {
            $digits = '88' . $digits;
        } elseif (! str_starts_with($digits, '88')) {
            $digits = '88' . ltrim($digits, '8');
        }

        $message ??= __('Hello :name, your appointment (serial :serial) on :date has been cancelled because the clinic is closed. Please contact us to rebook.', [
            'name' => $this->patient_name,
            'serial' => $this->serial_number,
            'date' => $this->booking_date?->translatedFormat('j F Y'),
        ]);

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    public function earlierDateWhatsappLink(): string
    {
        $message = __('Hello :name, an earlier appointment date may be available for your booking on :date (serial :serial). Please reply if you would like to move to an earlier date.', [
            'name' => $this->patient_name,
            'serial' => $this->serial_number,
            'date' => $this->booking_date?->translatedFormat('j F Y'),
        ]);

        return $this->whatsappLink($message);
    }

    public function missedProcedureWhatsappLink(): string
    {
        $message = __('Hello :name, you missed your procedure appointment on :date. Please reply so we can book another sitting.', [
            'name' => $this->patient_name,
            'date' => $this->booking_date?->translatedFormat('j F Y'),
        ]);

        return $this->whatsappLink($message);
    }

    public function slotBlock()
    {
        return $this->belongsTo(SlotBlock::class, 'slot_block_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitRecord()
    {
        return $this->hasOne(VisitRecord::class);
    }

    public function cashEntry()
    {
        return $this->hasOne(ChamberCashEntry::class);
    }

    public function relatedBooking()
    {
        return $this->belongsTo(Booking::class, 'related_booking_id');
    }

    public function referringDoctor()
    {
        return $this->belongsTo(ReferringDoctor::class);
    }

    public function referralCommission()
    {
        return $this->hasOne(ReferralCommission::class);
    }

    public function procedureBookings()
    {
        return $this->hasMany(Booking::class, 'related_booking_id');
    }

    /** @return array<string, string> */
    public static function procedureStatusOptions(): array
    {
        return [
            self::PROCEDURE_LOGGED => __('Logged'),
            self::PROCEDURE_PREPPED => __('Prepped'),
            self::PROCEDURE_DOCTOR_CALLED => __('Doctor called'),
            self::PROCEDURE_DONE => __('Done'),
        ];
    }

    public function voucherLabel(): ?string
    {
        return $this->voucher_number !== null
            ? (string) $this->voucher_number
            : null;
    }

    public function bookable()
    {
        return $this->morphTo();
    }

    public function labTests()
    {
        return $this->belongsToMany(LabTest::class)
            ->using(BookingLabTest::class)
            ->withPivot('price_at_booking')
            ->withTimestamps();
    }

    /** Sum of the line items as quoted at booking time, not today's prices. */
    public function totalPrice(): string
    {
        return number_format(
            (float) $this->labTests->sum(fn ($test) => (float) $test->pivot->price_at_booking),
            2,
            '.',
            ''
        );
    }

    /**
     * The union of every booked test's preparation instructions.
     *
     * Shown prominently on the ticket page — this is the screen the patient
     * re-reads the night before, and a missed fasting requirement means a
     * wasted test, a wasted trip, or a clinically wrong result.
     *
     * @return array<int, array{test: string, instructions: string}>
     */
    public function preparationInstructions(): array
    {
        return $this->labTests
            ->filter(fn ($test) => filled($test->preparation_instructions))
            ->map(fn ($test) => [
                'test' => $test->name,
                'instructions' => $test->preparation_instructions,
            ])
            ->values()
            ->all();
    }

    public function liveSession()
    {
        if ($this->bookable_type !== ScheduleSession::class) {
            return null;
        }

        return LiveSession::where('schedule_session_id', $this->bookable_id)
            ->where('session_date', $this->booking_date->toDateString())
            ->first();
    }
}
