<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacyItem extends Model
{
    use BelongsToTenant;

    public const UNIT_UNIT = 'unit';

    public const UNIT_STRIP = 'strip';

    public const UNIT_BOTTLE = 'bottle';

    public const UNIT_TUBE = 'tube';

    public const UNIT_PIECE = 'piece';

    protected $fillable = [
        'medicine_id',
        'name',
        'generic_name',
        'sell_price_taka',
        'company_share_taka',
        'unit_label',
        'qty_on_hand',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'sell_price_taka' => 'integer',
        'company_share_taka' => 'integer',
        'qty_on_hand' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return array<string, string> */
    public static function unitOptions(): array
    {
        return [
            self::UNIT_STRIP => __('Strip'),
            self::UNIT_BOTTLE => __('Bottle'),
            self::UNIT_TUBE => __('Tube'),
            self::UNIT_PIECE => __('Piece'),
            self::UNIT_UNIT => __('Unit'),
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PharmacyDelivery::class);
    }

    public function shopCutTaka(): int
    {
        return max(0, $this->sell_price_taka - $this->company_share_taka);
    }

    public function displayLabel(): string
    {
        $stock = __(' :qty in stock', ['qty' => $this->qty_on_hand]);

        return $this->name.' — ৳'.number_format($this->sell_price_taka).$stock;
    }

    public static function matchByBrand(?string $brand): ?self
    {
        $needle = mb_strtolower(trim((string) $brand));

        if ($needle === '') {
            return null;
        }

        return static::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [$needle])
            ->orderByDesc('qty_on_hand')
            ->first();
    }
}
