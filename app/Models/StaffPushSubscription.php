<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPushSubscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'endpoint_hash',
        'endpoint',
        'p256dh',
        'auth_token',
        'last_buzz_key',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->endpoint_hash = hash('sha256', (string) $model->endpoint);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
