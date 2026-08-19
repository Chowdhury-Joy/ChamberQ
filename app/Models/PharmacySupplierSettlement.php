<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacySupplierSettlement extends Model
{
    use BelongsToTenant;

    public const KIND_PURCHASE = 'purchase';

    public const KIND_REFUND = 'refund';

    protected $fillable = [
        'kind',
        'amount',
        'cash_entry_id',
        'recorded_by',
        'occurred_on',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'occurred_on' => DateOnly::class,
    ];

    public function cashEntry(): BelongsTo
    {
        return $this->belongsTo(ChamberCashEntry::class, 'cash_entry_id');
    }
}
