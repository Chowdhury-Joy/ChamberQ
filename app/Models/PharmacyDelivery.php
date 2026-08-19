<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacyDelivery extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'pharmacy_item_id',
        'qty_received',
        'qty_sold',
        'qty_returned',
        'qty_on_hand',
        'company_share_taka',
        'paid_taka',
        'returnable',
        'note',
        'received_by',
        'received_on',
    ];

    protected $casts = [
        'qty_received' => 'integer',
        'qty_sold' => 'integer',
        'qty_returned' => 'integer',
        'qty_on_hand' => 'integer',
        'company_share_taka' => 'integer',
        'paid_taka' => 'integer',
        'returnable' => 'boolean',
        'received_on' => DateOnly::class,
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PharmacyItem::class, 'pharmacy_item_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(PharmacySaleItem::class);
    }

    public function shouldGetTaka(): int
    {
        $units = $this->returnable ? $this->qty_sold : $this->qty_received;

        return $units * $this->company_share_taka;
    }

    public function owedTaka(): int
    {
        return max(0, $this->shouldGetTaka() - $this->paid_taka);
    }

    public function refundDueTaka(): int
    {
        return max(0, $this->paid_taka - $this->shouldGetTaka());
    }
}
