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
        'allows_repeat_serials',
        'collect_fee_at_checkin',
        'practice_rules',
        'pharmacy_doctor_percent',
        'qualifications',
        'registration_number',
        'default_fee_taka',
        'extra_fees',
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
        'allows_repeat_serials' => 'boolean',
        'default_fee_taka' => 'integer',
        'extra_fees' => 'array',
        'practice_rules' => 'array',
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

    public const FEE_CONSULTATION = 'consultation';

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

    public const CHANNEL_AUTO_SMS = 'auto_sms';

    public const CHANNEL_PUSH_SMS = 'push_sms';

    public const CHANNEL_PUSH_WHATSAPP = 'push_whatsapp';

    /**
     * Stages where the old `sms` toggle meant ChamberQ sent the text itself.
     *
     * @var list<string>
     */
    private const LEGACY_AUTO_SMS_STAGES = [
        self::NOTIFY_BOOKING_CONFIRMATION,
        self::NOTIFY_DOCTOR_LATE,
        self::NOTIFY_CANCELLATION,
        self::NOTIFY_FOLLOW_UP,
    ];

    /**
     * Stages where the old `sms` toggle meant staff tapped Send SMS.
     * Cancellation is in both lists: end-session auto-sent, vacation was a tap.
     *
     * @var list<string>
     */
    private const LEGACY_PUSH_SMS_STAGES = [
        self::NOTIFY_CANCELLATION,
        self::NOTIFY_PRESCRIPTION,
    ];

    /**
     * Defaults match today's product behaviour so existing clinics keep the
     * same outbound mix until a doctor edits them.
     *
     * @return array<string, array{auto_sms: bool, push_sms: bool, push_whatsapp: bool}>
     */
    public static function defaultNotifyChannels(): array
    {
        return [
            self::NOTIFY_BOOKING_CONFIRMATION => [
                self::CHANNEL_AUTO_SMS => true,
                self::CHANNEL_PUSH_SMS => false,
                self::CHANNEL_PUSH_WHATSAPP => false,
            ],
            self::NOTIFY_DOCTOR_LATE => [
                self::CHANNEL_AUTO_SMS => false,
                self::CHANNEL_PUSH_SMS => false,
                self::CHANNEL_PUSH_WHATSAPP => false,
            ],
            self::NOTIFY_CANCELLATION => [
                self::CHANNEL_AUTO_SMS => false,
                self::CHANNEL_PUSH_SMS => false,
                self::CHANNEL_PUSH_WHATSAPP => true,
            ],
            self::NOTIFY_PRESCRIPTION => [
                self::CHANNEL_AUTO_SMS => false,
                self::CHANNEL_PUSH_SMS => false,
                self::CHANNEL_PUSH_WHATSAPP => true,
            ],
            self::NOTIFY_FOLLOW_UP => [
                self::CHANNEL_AUTO_SMS => true,
                self::CHANNEL_PUSH_SMS => false,
                self::CHANNEL_PUSH_WHATSAPP => false,
            ],
        ];
    }

    /**
     * Labels for the three delivery choices every stage offers.
     *
     * @return array<string, string>
     */
    public static function notifyDeliveryOptions(): array
    {
        return [
            self::CHANNEL_AUTO_SMS => __('Auto SMS'),
            self::CHANNEL_PUSH_SMS => __('Push SMS'),
            self::CHANNEL_PUSH_WHATSAPP => __('Push WhatsApp'),
        ];
    }

    /**
     * @param  array<string, mixed>|list<string>|null  $state
     * @return list<string>
     */
    public static function selectedNotifyDeliveries(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        if (array_is_list($state)) {
            return array_values(array_filter(
                $state,
                fn (mixed $key): bool => in_array($key, [
                    self::CHANNEL_AUTO_SMS,
                    self::CHANNEL_PUSH_SMS,
                    self::CHANNEL_PUSH_WHATSAPP,
                ], true),
            ));
        }

        $selected = [];

        foreach ([self::CHANNEL_AUTO_SMS, self::CHANNEL_PUSH_SMS, self::CHANNEL_PUSH_WHATSAPP] as $key) {
            if (! empty($state[$key])) {
                $selected[] = $key;
            }
        }

        return $selected;
    }

    /**
     * @param  list<string>|array<string, mixed>|null  $state
     * @return array{auto_sms: bool, push_sms: bool, push_whatsapp: bool}
     */
    public static function notifyDeliveriesFromSelection(mixed $state): array
    {
        $keys = is_array($state) ? $state : [];

        return [
            self::CHANNEL_AUTO_SMS => in_array(self::CHANNEL_AUTO_SMS, $keys, true),
            self::CHANNEL_PUSH_SMS => in_array(self::CHANNEL_PUSH_SMS, $keys, true),
            self::CHANNEL_PUSH_WHATSAPP => in_array(self::CHANNEL_PUSH_WHATSAPP, $keys, true),
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
     * Null on the doctor row means inherit Branding → Desk.
     */
    public function collectsFeeAtCheckin(?bool $clinicDefault = null): bool
    {
        $raw = $this->attributes['collect_fee_at_checkin'] ?? null;

        if ($raw === null || $raw === '') {
            return (bool) ($clinicDefault ?? tenant()?->collectsFeeAtCheckin());
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, array{label: string, amount: int}>
     */
    public function feeTypes(): array
    {
        return [
            self::FEE_CONSULTATION => [
                'label' => 'Consultation',
                'amount' => (int) ($this->default_fee_taka ?? 0),
            ],
            ...$this->normalizedExtraFees(),
        ];
    }

    public function hasExtraFeeTypes(): bool
    {
        return $this->normalizedExtraFees() !== [];
    }

    /**
     * @return array<string, array{label: string, amount: int}>
     */
    public function normalizedExtraFees(): array
    {
        $rows = $this->extra_fees;
        if (! is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $amount = (int) ($row['amount'] ?? 0);

            if ($label === '' || $amount < 1) {
                continue;
            }

            $slug = Str::slug($label);
            if ($slug === '' || $slug === self::FEE_CONSULTATION) {
                $slug = substr(hash('sha256', $label), 0, 12);
            }

            $out['extra:'.$slug] = [
                'label' => $label,
                'amount' => $amount,
            ];
        }

        return $out;
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
     * @return array<string, array{auto_sms: bool, push_sms: bool, push_whatsapp: bool}>
     */
    public function notifyChannels(): array
    {
        $stored = is_array($this->notify_channels) ? $this->notify_channels : [];
        $defaults = self::defaultNotifyChannels();
        $merged = [];

        foreach ($defaults as $stage => $channels) {
            $row = is_array($stored[$stage] ?? null) ? $stored[$stage] : [];
            $merged[$stage] = [
                self::CHANNEL_AUTO_SMS => self::channelFromRow(
                    $row,
                    self::CHANNEL_AUTO_SMS,
                    'sms',
                    $channels[self::CHANNEL_AUTO_SMS],
                    in_array($stage, self::LEGACY_AUTO_SMS_STAGES, true),
                ),
                self::CHANNEL_PUSH_SMS => self::channelFromRow(
                    $row,
                    self::CHANNEL_PUSH_SMS,
                    'sms',
                    $channels[self::CHANNEL_PUSH_SMS],
                    in_array($stage, self::LEGACY_PUSH_SMS_STAGES, true),
                ),
                self::CHANNEL_PUSH_WHATSAPP => self::channelFromRow(
                    $row,
                    self::CHANNEL_PUSH_WHATSAPP,
                    'whatsapp',
                    $channels[self::CHANNEL_PUSH_WHATSAPP],
                    true,
                ),
            ];
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function channelFromRow(
        array $row,
        string $newKey,
        string $legacyKey,
        bool $default,
        bool $legacyMapsToThisChannel,
    ): bool {
        if (array_key_exists($legacyKey, $row)) {
            return (bool) $row[$legacyKey] && $legacyMapsToThisChannel;
        }

        if (array_key_exists($newKey, $row)) {
            return (bool) $row[$newKey];
        }

        return $default;
    }

    public function wantsAutoSms(string $stage): bool
    {
        return (bool) ($this->notifyChannels()[$stage][self::CHANNEL_AUTO_SMS] ?? false);
    }

    public function wantsPushSms(string $stage): bool
    {
        return (bool) ($this->notifyChannels()[$stage][self::CHANNEL_PUSH_SMS] ?? false);
    }

    public function wantsSms(string $stage): bool
    {
        return $this->wantsAutoSms($stage) || $this->wantsPushSms($stage);
    }

    public function wantsWhatsapp(string $stage): bool
    {
        return (bool) ($this->notifyChannels()[$stage][self::CHANNEL_PUSH_WHATSAPP] ?? false);
    }

    public function wantsStaffTap(string $stage): bool
    {
        return $this->wantsPushSms($stage) || $this->wantsWhatsapp($stage);
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

    /**
     * Percent of the shop cut owed to this prescribing doctor. Null on the
     * profile means use the chamber default. 0 means off for this doctor.
     */
    public function pharmacyCutPercent(?Tenant $tenant = null): int
    {
        if ($this->pharmacy_doctor_percent !== null) {
            return max(0, min(100, (int) $this->pharmacy_doctor_percent));
        }

        $tenant ??= tenant();

        return max(0, min(100, (int) ($tenant?->pharmacy_doctor_percent ?? 0)));
    }
}
