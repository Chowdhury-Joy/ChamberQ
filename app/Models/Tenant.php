<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasDomains;

    /**
     * Request-lifetime cache for `hasUserInQueueRole()`. Declared as a real PHP
     * property so Eloquent's magic accessors (and VirtualColumn's `data` blob)
     * never see it as an attribute.
     *
     * @var array<string, bool>
     */
    private array $queueRolePresence = [];

    public const DEFAULT_THEME_COLOR = '#2563eb';

    /** Hex colour safe to emit into CSS (`--brand`). Anything else blanks the clinic hero. */
    public static function isCssColor(?string $value): bool
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value)) === 1;
    }

    public function cssThemeColor(): string
    {
        $value = trim((string) ($this->getAttributes()['theme_color'] ?? ''));

        return self::isCssColor($value) ? $value : self::DEFAULT_THEME_COLOR;
    }

    /** Default browser tab icon when a tenant has not uploaded a custom favicon. */
    public const DEFAULT_FAVICON = '/icons/health-favicon.svg';

    /** Max chambers on Solo when multiple_chambers is enabled. Clinic has no cap. */
    public const SOLO_MAX_CHAMBERS = 5;

    /** Months recorded by Super Admin “Confirm 12 months prepaid”. */
    public const PREPAID_YEAR_MONTHS = 12;

    /**
     * Sellable product modules (independent of Solo/Clinic size tier).
     * Stored in `feature_flags`; absent key = on (existing chambers keep full product).
     */
    public const MODULE_FRONT_DOOR = 'front_door';

    public const MODULE_LIVE_QUEUE = 'live_queue';

    public const MODULE_PRESCRIPTION = 'prescription';

    /** Opt-in clinic module: rooms + split till. Absent flag = off (not default-on). */
    public const MODULE_STATIONS = 'stations';

    /** Opt-in: external GP referral commissions. Absent flag = off. */
    public const MODULE_REFERRALS = 'referrals';

    /** Opt-in: staff HR (attendance, leave, payroll). Absent flag = off. */
    public const MODULE_HR = 'hr';

    /** Opt-in: in-chamber pharmacy counter + stock. Absent flag = off. */
    public const MODULE_PHARMACY = 'pharmacy';

    /** @return list<string> */
    public static function productModules(): array
    {
        return [
            self::MODULE_FRONT_DOOR,
            self::MODULE_LIVE_QUEUE,
            self::MODULE_PRESCRIPTION,
        ];
    }

    /** @return array<string, string> */
    public static function productModuleOptions(): array
    {
        return [
            self::MODULE_FRONT_DOOR => 'Front door — website + online booking + day list (ticket shows sitting window, not come-around)',
            self::MODULE_LIVE_QUEUE => 'Live queue — outdoor TV, Call next, live ticket updates + come-around time',
            self::MODULE_PRESCRIPTION => 'Prescription — consult pad, digital Rx, medicines, follow-ups',
        ];
    }

    public const ETA_SCHEDULE_GUESS = 'schedule_guess';

    public const ETA_LIVE_AVERAGE = 'live_average';

    public const ETA_LIVE_STEADY = 'live_steady';

    public const ANNOUNCE_CHIME = 'chime';

    public const ANNOUNCE_VOICE = 'voice';

    public const ANNOUNCE_CHIME_AND_VOICE = 'chime_and_voice';

    public const QUEUE_RUNNER_STAFF = 'staff';

    public const QUEUE_RUNNER_DOCTOR = 'doctor';

    /** @return array<string, string> */
    public static function etaModelOptions(): array
    {
        return [
            self::ETA_SCHEDULE_GUESS => 'Schedule guess (session length ÷ seats)',
            self::ETA_LIVE_AVERAGE => 'Live average (today’s finished consults)',
            self::ETA_LIVE_STEADY => 'Live steady (ignore longest + shortest)',
        ];
    }

    /** @return array<string, string> */
    public static function callAnnounceModeOptions(): array
    {
        return [
            self::ANNOUNCE_CHIME => 'Chime only',
            self::ANNOUNCE_VOICE => 'Voice only (“Calling number…”)',
            self::ANNOUNCE_CHIME_AND_VOICE => 'Chime + voice',
        ];
    }

    /** @return array<string, string> */
    public static function queueRunnerOptions(): array
    {
        return [
            self::QUEUE_RUNNER_STAFF => 'Staff-run (default) — staff call patients; doctor consult screen follows',
            self::QUEUE_RUNNER_DOCTOR => 'Doctor-run — doctor calls patients; staff see no queue controls',
        ];
    }

    public function queueRunner(): string
    {
        $runner = $this->queue_runner ?? self::QUEUE_RUNNER_STAFF;

        return in_array($runner, [self::QUEUE_RUNNER_STAFF, self::QUEUE_RUNNER_DOCTOR], true)
            ? $runner
            : self::QUEUE_RUNNER_STAFF;
    }

    /**
     * The party that can actually work the queue right now.
     *
     * `queueRunner()` is the configured choice; this is that choice checked
     * against reality. A practice with no user in the configured role would
     * otherwise have nobody able to call patients — the default is staff-run,
     * so a solo doctor working without staff was locked out of their own
     * chamber and could not fix it (only an admin can change the setting).
     *
     * Exclusivity still holds: this only ever hands controls over when the
     * configured party has no one to hand them to, so two parties are never
     * live at once.
     */
    public function effectiveQueueRunner(): string
    {
        $configured = $this->queueRunner();

        if ($this->hasUserInQueueRole($configured)) {
            return $configured;
        }

        $fallback = $configured === self::QUEUE_RUNNER_STAFF
            ? self::QUEUE_RUNNER_DOCTOR
            : self::QUEUE_RUNNER_STAFF;

        return $this->hasUserInQueueRole($fallback) ? $fallback : $configured;
    }

    /** Memoised per request — this is consulted on every panel page load. */
    private function hasUserInQueueRole(string $runner): bool
    {
        $role = $runner === self::QUEUE_RUNNER_DOCTOR
            ? User::ROLE_DOCTOR
            : User::ROLE_STAFF;

        return $this->queueRolePresence[$role] ??= User::query()
            ->where('tenant_id', $this->getTenantKey())
            ->where('role', $role)
            ->exists();
    }

    public function hasStaffLogin(): bool
    {
        return $this->hasUserInQueueRole(self::QUEUE_RUNNER_STAFF);
    }

    public function isStaffRunQueue(): bool
    {
        return $this->queueRunner() === self::QUEUE_RUNNER_STAFF;
    }

    public function isDoctorRunQueue(): bool
    {
        return $this->queueRunner() === self::QUEUE_RUNNER_DOCTOR;
    }

    public function usesCallChime(): bool
    {
        $mode = $this->call_announce_mode ?? self::ANNOUNCE_CHIME_AND_VOICE;

        return in_array($mode, [self::ANNOUNCE_CHIME, self::ANNOUNCE_CHIME_AND_VOICE], true);
    }

    public function usesCallVoice(): bool
    {
        $mode = $this->call_announce_mode ?? self::ANNOUNCE_CHIME_AND_VOICE;

        return in_array($mode, [self::ANNOUNCE_VOICE, self::ANNOUNCE_CHIME_AND_VOICE], true);
    }

    /**
     * When false (default), Collect fee is a primary after the visit.
     * When true, staff also see it on waiting / just-arrived patients.
     */
    public function collectsFeeAtCheckin(): bool
    {
        return (bool) $this->collect_fee_at_checkin;
    }

    public static function getCustomColumns(): array
    {
        // Every real column MUST be listed here. Anything omitted is folded into
        // the `data` JSON blob by stancl's VirtualColumn: PHP attribute reads
        // appear to work while SQL filters silently match nothing.
        return [
            'id',
            'name',
            'contact_phone',
            'whatsapp_number',
            'review_url',
            'theme_color',
            'logo_url',
            'favicon_url',
            'font_family',
            'tagline',
            'default_locale',
            'template_id',
            'layout_id',
            'custom_code',
            'custom_code_approved_at',
            'billing_status',
            'sms_balance',
            'plan_tier',
            'marketer_id',
            'medical_representative_id',
            'discount_code_id',
            'list_setup_amount',
            'list_monthly_amount',
            'setup_amount_due',
            'monthly_amount_due',
            'paying_setup_amount',
            'paying_monthly_amount',
            'referral_note',
            'referred_at',
            'setup_paid_at',
            'offer_prescription_lifetime_free',
            'offer_prepaid_year_setup',
            'commission_setup_mr_rate',
            'commission_setup_marketer_rate',
            'commission_year1_prepaid_mr_rate',
            'commission_year1_prepaid_marketer_rate',
            'commission_year2_mr_rate',
            'commission_year2_marketer_rate',
            'slot_cap_mode',
            'feature_flags',
            'call_timeout_seconds',
            'estimated_time_buffer_minutes',
            'eta_model',
            'first_n_patients',
            'first_n_arrival_offset_minutes',
            'call_audio_preset',
            'call_audio_path',
            'call_announce_mode',
            'call_announce_locale',
            'queue_runner',
            'collect_fee_at_checkin',
            'patient_booking_horizon_days',
            'pharmacy_doctor_percent',
            'practice_rules',
            'created_at',
            'updated_at',
        ];
    }

    /** The name patients see. Falls back to the subdomain rather than showing nothing. */
    public function displayName(): string
    {
        return filled($this->name) ? $this->name : (string) $this->id;
    }

    /** Browser tab icon: custom upload, else the shared health cross. */
    public function faviconHref(): string
    {
        return filled($this->favicon_url) ? (string) $this->favicon_url : self::DEFAULT_FAVICON;
    }

    /**
     * Public URL for the waiting-room call chime.
     * Relative paths keep tenant domains (e.g. solo.localhost) working.
     */
    public function callAudioUrl(): string
    {
        $preset = $this->call_audio_preset ?? 'chime';

        if ($preset === 'custom' && filled($this->call_audio_path)) {
            return tenant_web_url('/storage/'.ltrim((string) $this->call_audio_path, '/'));
        }

        return match ($preset) {
            'soft-bell' => '/audio/soft-bell.wav',
            'alert' => '/audio/alert.wav',
            default => '/audio/chime.wav',
        };
    }

    protected function casts(): array
    {
        return [
            'feature_flags' => 'array',
            'practice_rules' => 'array',
            'custom_code_approved_at' => 'datetime',
            'sms_balance' => 'integer',
            'list_setup_amount' => 'integer',
            'list_monthly_amount' => 'integer',
            'setup_amount_due' => 'integer',
            'monthly_amount_due' => 'integer',
            'paying_setup_amount' => 'integer',
            'paying_monthly_amount' => 'integer',
            'referred_at' => 'datetime',
            'setup_paid_at' => 'datetime',
            'offer_prescription_lifetime_free' => 'boolean',
            'patient_booking_horizon_days' => 'integer',
            'pharmacy_doctor_percent' => 'integer',
            'offer_prepaid_year_setup' => 'boolean',
            'commission_setup_mr_rate' => 'float',
            'commission_setup_marketer_rate' => 'float',
            'commission_year1_prepaid_mr_rate' => 'float',
            'commission_year1_prepaid_marketer_rate' => 'float',
            'commission_year2_mr_rate' => 'float',
            'commission_year2_marketer_rate' => 'float',
        ];
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function medicalRepresentative(): BelongsTo
    {
        return $this->belongsTo(MedicalRepresentative::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /**
     * 1 = first 12 billing months after setup was marked paid; 2+ after that.
     */
    public function serviceYearForPeriod(string $period): int
    {
        $start = $this->setup_paid_at
            ? $this->setup_paid_at->copy()->startOfMonth()
            : now()->startOfMonth();
        $target = \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)?->startOfMonth();
        if (! $target) {
            return 1;
        }

        $months = (($target->year - $start->year) * 12) + ($target->month - $start->month);
        if ($months < 0) {
            return 1;
        }

        return intdiv($months, 12) + 1;
    }

    public function billingPayments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function hasSetupPaid(): bool
    {
        return $this->setup_paid_at !== null;
    }

    public function hasFeature(string $feature): bool
    {
        // Stations is opt-in only — never inherit a tier default.
        if (in_array($feature, [self::MODULE_STATIONS, self::MODULE_REFERRALS, self::MODULE_HR, self::MODULE_PHARMACY], true)) {
            $flags = $this->feature_flags ?? [];

            if (! array_key_exists($feature, $flags)) {
                return false;
            }

            return filter_var($flags[$feature], FILTER_VALIDATE_BOOLEAN);
        }

        // Check feature_flags JSON column first
        $flags = $this->feature_flags ?? [];
        if (array_key_exists($feature, $flags)) {
            // Filament KeyValue stores string "true"/"false"; (bool)"false" === true.
            return filter_var($flags[$feature], FILTER_VALIDATE_BOOLEAN);
        }

        // Product modules default ON so existing tenants keep the full product
        // until Super Admin explicitly unchecks one.
        if (in_array($feature, self::productModules(), true)) {
            return true;
        }

        // Fall back to tier defaults
        return match ($this->plan_tier) {
            'solo' => match ($feature) {
                'lab_tests' => false,
                'multiple_chambers' => true,
                'multiple_doctors' => false,
                'bangla_homepage' => false,
                default => false,
            },
            'clinic' => match ($feature) {
                'lab_tests' => true,
                'multiple_chambers' => true,
                'multiple_doctors' => true,
                'bangla_homepage' => false,
                default => false,
            },
            default => false,
        };
    }

    public function hasFrontDoor(): bool
    {
        return $this->hasFeature(self::MODULE_FRONT_DOOR);
    }

    public function hasLiveQueue(): bool
    {
        return $this->hasFeature(self::MODULE_LIVE_QUEUE);
    }

    public function hasPrescription(): bool
    {
        return $this->hasFeature(self::MODULE_PRESCRIPTION);
    }

    public function hasStations(): bool
    {
        return $this->hasFeature(self::MODULE_STATIONS);
    }

    public function hasReferrals(): bool
    {
        return $this->hasFeature(self::MODULE_REFERRALS);
    }

    public function hasHr(): bool
    {
        return $this->hasFeature(self::MODULE_HR);
    }

    public function hasPharmacy(): bool
    {
        return $this->hasFeature(self::MODULE_PHARMACY);
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return array<string, mixed>
     */
    public static function mergeOptInModuleFlag(array $flags, string $module, bool $enabled): array
    {
        $flags[$module] = $enabled;

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return array<string, mixed>
     */
    public static function mergeStationsFlag(array $flags, bool $enabled): array
    {
        return self::mergeOptInModuleFlag($flags, self::MODULE_STATIONS, $enabled);
    }

    /**
     * Merge Super Admin module checkboxes into feature_flags without wiping
     * add-ons (lab_tests, bangla_homepage, …).
     *
     * @param  list<string>  $enabledModules
     * @param  array<string, mixed>|null  $existingFlags
     * @return array<string, mixed>
     */
    public static function featureFlagsWithModules(?array $existingFlags, array $enabledModules): array
    {
        $flags = is_array($existingFlags) ? $existingFlags : [];

        foreach (self::productModules() as $module) {
            $flags[$module] = in_array($module, $enabledModules, true);
        }

        return $flags;
    }

    /**
     * @return list<string>
     */
    public function enabledProductModules(): array
    {
        return array_values(array_filter(
            self::productModules(),
            fn (string $module): bool => $this->hasFeature($module),
        ));
    }

    public static function planTierLabel(?string $planTier): string
    {
        if ($planTier === 'clinic') {
            return (string) (config('marketing.plans.clinic.name') ?? 'Clinic');
        }

        return (string) (config('marketing.plans.solo.name') ?? 'Maestro');
    }

    /**
     * Short chips for Super Admin / partner lists (Website / Queue / Rx).
     *
     * @return list<string>
     */
    public function productModuleChipLabels(): array
    {
        $labels = [
            self::MODULE_FRONT_DOOR => 'Website',
            self::MODULE_LIVE_QUEUE => 'Queue',
            self::MODULE_PRESCRIPTION => 'Rx',
        ];

        $chips = [];
        foreach (self::productModules() as $module) {
            if ($this->hasFeature($module)) {
                $chips[] = $labels[$module] ?? $module;
            }
        }

        return $chips;
    }

    public function isClinic(): bool
    {
        return $this->plan_tier === 'clinic';
    }

    public function isSoloDoctor(): bool
    {
        return ! $this->isClinic();
    }

    /**
     * Chamber limit for this tenant. Null means unlimited (Clinic tier).
     * When multiple_chambers is off via feature flag, cap is 1.
     */
    public function maxChambers(): ?int
    {
        if (! $this->hasFeature('multiple_chambers')) {
            return 1;
        }

        return $this->isClinic() ? null : self::SOLO_MAX_CHAMBERS;
    }

    /**
     * Whether the public booking API may create new serials.
     *
     * Trial/active keep taking bookings. Past-due, suspended, and legacy
     * read_only close online booking while the site and admin stay usable.
     */
    public function acceptsBookings(): bool
    {
        if (! $this->hasFrontDoor()) {
            return false;
        }

        return ! in_array($this->billing_status, [
            'past_due',
            'suspended',
            'read_only',
        ], true);
    }
}
