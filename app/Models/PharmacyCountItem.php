<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyCountItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pharmacy_count_id',
        'pharmacy_item_id',
        'system_qty',
        'counted_qty',
        'difference',
    ];

    protected $casts = [
        'system_qty' => 'integer',
        'counted_qty' => 'integer',
        'difference' => 'integer',
    ];

    public function count(): BelongsTo
    {
        return $this->belongsTo(PharmacyCount::class, 'pharmacy_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PharmacyItem::class, 'pharmacy_item_id');
    }
}
