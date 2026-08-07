<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use BelongsToTenant;
    
    protected $fillable = [
        'name',
        'user_id',
        'practice_type',
        'staff_may_enter_prescriptions',
        'qualifications',
        'registration_number',
        'notify_channels',
    ];

    protected $casts = [
        'staff_may_enter_prescriptions' => 'boolean',
        'notify_channels' => 'array',
    ];

    public const PRACTICE_GENERAL = 'general_physician';

    public const PRACTICE_GYNECOLOGIST = 'gynecologist';

    public const PRACTICE_DENTIST = 'dentist';

    public const PRACTICE_PEDIATRICIAN = 'pediatrician';

    public const PRACTICE_CARDIOLOGIST = 'cardiologist';

    public const PRACTICE_DERMATOLOGIST = 'dermatologist';

    public const NOTIFY_BOOKING_CONFIRMATION = 'booking_confirmation';

    public const NOTIFY_DOCTOR_LATE = 'doctor_late';

    public const NOTIFY_CANCELLATION = 'cancellation';

    public const NOTIFY_PRESCRIPTION = 'prescription';

    /**
     * Defaults match today's product behaviour so existing clinics keep the
     * same outbound mix until a doctor edits them.
     *
     * @return array<string, array{sms: bool, whatsapp: bool}>
     */
    public static function defaultNotifyChannels(): array
    {
        return [
            self::NOTIFY_BOOKING_CONFIRMATION => ['sms' => true, 'whatsapp' => false],
            self::NOTIFY_DOCTOR_LATE => ['sms' => false, 'whatsapp' => false],
            self::NOTIFY_CANCELLATION => ['sms' => false, 'whatsapp' => true],
            self::NOTIFY_PRESCRIPTION => ['sms' => false, 'whatsapp' => true],
        ];
    }

    /**
     * Doctor for a booking's schedule session, or the practice's first doctor
     * when the bookable has no doctor (e.g. lab-only rows).
     */
    public static function resolveForBooking(Booking $booking): ?self
    {
        if ($booking->bookable_type === ScheduleSession::class) {
            $booking->loadMissing('bookable.doctor');

            if ($booking->bookable?->doctor) {
                return $booking->bookable->doctor;
            }
        }

        return static::query()->orderBy('id')->first();
    }

    /**
     * The login this doctor signs in with, when one has been matched.
     *
     * Optional: a practice may list a visiting doctor who never logs in, and
     * a clinic admin may not have paired accounts to profiles yet.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    public static function practiceTypeOptions(): array
    {
        return [
            self::PRACTICE_GENERAL => __('General physician'),
            self::PRACTICE_GYNECOLOGIST => __('Gynecologist'),
            self::PRACTICE_DENTIST => __('Dentist'),
            self::PRACTICE_PEDIATRICIAN => __('Pediatrician'),
            self::PRACTICE_CARDIOLOGIST => __('Cardiologist'),
            self::PRACTICE_DERMATOLOGIST => __('Dermatologist'),
        ];
    }

    public function practiceTypeLabel(): string
    {
        return self::practiceTypeOptions()[$this->practice_type ?? self::PRACTICE_GENERAL]
            ?? (string) $this->practice_type;
    }

    /**
     * This doctor writes on paper and lets staff key it in afterwards.
     *
     * There is no per-prescription approval step: turning this on IS the
     * doctor's standing permission, so the switch itself is the safeguard.
     */
    public function allowsStaffPrescriptionEntry(): bool
    {
        return (bool) $this->staff_may_enter_prescriptions;
    }

    /**
     * @return array<string, array{sms: bool, whatsapp: bool}>
     */
    public function notifyChannels(): array
    {
        $stored = is_array($this->notify_channels) ? $this->notify_channels : [];
        $defaults = self::defaultNotifyChannels();
        $merged = [];

        foreach ($defaults as $stage => $channels) {
            $row = $stored[$stage] ?? [];
            $merged[$stage] = [
                'sms' => array_key_exists('sms', $row) ? (bool) $row['sms'] : $channels['sms'],
                'whatsapp' => array_key_exists('whatsapp', $row) ? (bool) $row['whatsapp'] : $channels['whatsapp'],
            ];
        }

        return $merged;
    }

    public function wantsSms(string $stage): bool
    {
        return (bool) ($this->notifyChannels()[$stage]['sms'] ?? false);
    }

    public function wantsWhatsapp(string $stage): bool
    {
        return (bool) ($this->notifyChannels()[$stage]['whatsapp'] ?? false);
    }
}
