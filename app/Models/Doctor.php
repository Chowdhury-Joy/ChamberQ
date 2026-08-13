<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\HtmlSanitizer;
use App\Support\PublicStoredImage;
use App\Support\SafeUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'default_fee_taka',
        'public_slug',
        'public_title',
        'bio',
        'photo_url',
        'show_on_website',
        'website_sort_order',
        'notify_channels',
    ];

    protected $casts = [
        'staff_may_enter_prescriptions' => 'boolean',
        'default_fee_taka' => 'integer',
        'show_on_website' => 'boolean',
        'website_sort_order' => 'integer',
        'notify_channels' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $doctor): void {
            if (filled($doctor->public_slug)) {
                $doctor->public_slug = Str::slug($doctor->public_slug);
            } elseif ($doctor->show_on_website && filled($doctor->name)) {
                $doctor->public_slug = Str::slug($doctor->name);
            }

            if (filled($doctor->bio)) {
                $doctor->bio = HtmlSanitizer::clean($doctor->bio);
            }

            // An uploaded photo arrives as a disk path with no scheme; turn it
            // into /storage/… before the scrub, or the save would wipe it.
            if (filled($doctor->photo_url)) {
                $doctor->photo_url = SafeUrl::href(
                    PublicStoredImage::toPublicPath($doctor->photo_url),
                    '',
                );
            }
        });
    }

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

    public const NOTIFY_FOLLOW_UP = 'follow_up';

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
            self::NOTIFY_FOLLOW_UP => ['sms' => true, 'whatsapp' => false],
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

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublishedOnWebsite(Builder $query): Builder
    {
        return $query
            ->where('show_on_website', true)
            ->whereNotNull('public_slug')
            ->where('public_slug', '!=', '')
            ->orderBy('website_sort_order')
            ->orderBy('name');
    }

    public function websiteSpecialtyLabel(): string
    {
        if (filled($this->public_title)) {
            return $this->public_title;
        }

        if (filled($this->qualifications)) {
            return $this->qualifications;
        }

        return $this->practiceTypeLabel();
    }
}
