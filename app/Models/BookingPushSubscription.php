<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPushSubscription extends Model
{
    use BelongsToTenant;

    public const STAGE_TWO_AWAY = 'two_away';

    public const STAGE_NEXT = 'next';

    public const STAGE_CALLED = 'called';

    public const STAGE_RANK = [
        self::STAGE_TWO_AWAY => 1,
        self::STAGE_NEXT => 2,
        self::STAGE_CALLED => 3,
    ];

    protected $fillable = [
        'booking_id',
        'endpoint_hash',
        'endpoint',
        'p256dh',
        'auth_token',
        'last_stage',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->endpoint_hash = hash('sha256', (string) $model->endpoint);
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function alreadySent(string $stage): bool
    {
        $last = self::STAGE_RANK[$this->last_stage] ?? 0;
        $next = self::STAGE_RANK[$stage] ?? 0;

        return $next <= $last;
    }
}
