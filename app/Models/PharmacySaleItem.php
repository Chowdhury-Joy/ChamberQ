<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacySaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pharmacy_sale_id',
        'pharmacy_item_id',
        'pharmacy_delivery_id',
        'prescription_item_id',
        'name',
        'qty',
        'sell_price_taka',
        'company_share_taka',
        'shop_cut_taka',
        'line_total_taka',
    ];

    protected $casts = [
        'qty' => 'integer',
        'sell_price_taka' => 'integer',
        'company_share_taka' => 'integer',
        'shop_cut_taka' => 'integer',
        'line_total_taka' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PharmacySale::class, 'pharmacy_sale_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PharmacyItem::class, 'pharmacy_item_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(PharmacyDelivery::class, 'pharmacy_delivery_id');
    }
}
