<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyStockAdjustment extends Model
{
    use BelongsToTenant;

    public const KIND_RECEIVE = 'receive';

    public const KIND_RETURN = 'return';

    public const KIND_SALE = 'sale';

    public const KIND_VOID_RESTORE = 'void_restore';

    public const KIND_COUNT_APPLY = 'count_apply';

    protected $fillable = [
        'pharmacy_item_id',
        'pharmacy_delivery_id',
        'kind',
        'qty_delta',
        'qty_before',
        'qty_after',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'qty_delta' => 'integer',
        'qty_before' => 'integer',
        'qty_after' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PharmacyItem::class, 'pharmacy_item_id');
    }
}
