<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    use BelongsToTenant;

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED_NO_BALANCE = 'skipped_no_balance';

    public const STATUS_SKIPPED_DISABLED = 'skipped_disabled';

    public const PURPOSE_BOOKING_CONFIRMATION = 'booking_confirmation';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'to',
        'body',
        'purpose',
        'status',
        'credits',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
