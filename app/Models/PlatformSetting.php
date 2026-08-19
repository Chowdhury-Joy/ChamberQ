<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    public $incrementing = false;

    public const SINGLETON_ID = 1;

    /**
     * First-install fallback when the platform_settings row does not exist yet.
     * Live windows come from Super Admin Booking window and optional per-tenant override.
     */
    public const DEFAULT_HORIZON_DAYS = 60;

    public const MIN_HORIZON_DAYS = 1;

    public const MAX_HORIZON_DAYS = 365;

    protected $fillable = [
        'patient_booking_horizon_days',
    ];

    protected function casts(): array
    {
        return [
            'patient_booking_horizon_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID],
            ['patient_booking_horizon_days' => self::DEFAULT_HORIZON_DAYS],
        );
    }

    /**
     * How far ahead a patient may book on this Front door.
     *
     * A chamber may set its own window; otherwise Super Admin's platform default.
     */
    public static function platformHorizonDays(): int
    {
        $days = (int) (static::query()->value('patient_booking_horizon_days') ?? self::DEFAULT_HORIZON_DAYS);

        return max(self::MIN_HORIZON_DAYS, min(self::MAX_HORIZON_DAYS, $days));
    }

    public static function patientBookingHorizonDays(?Tenant $tenant = null): int
    {
        $tenant ??= tenant();
        $override = $tenant?->patient_booking_horizon_days;
        if ($override !== null && (int) $override >= self::MIN_HORIZON_DAYS) {
            return max(self::MIN_HORIZON_DAYS, min(self::MAX_HORIZON_DAYS, (int) $override));
        }

        return static::platformHorizonDays();
    }

    public static function onlineBookingMaxDate(?Tenant $tenant = null): string
    {
        return now()->addDays(static::patientBookingHorizonDays($tenant))->toDateString();
    }
}
