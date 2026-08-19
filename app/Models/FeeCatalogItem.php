<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeCatalogItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'label',
        'list_price_taka',
        'house_share_taka',
        'sitting_kind',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'list_price_taka' => 'integer',
        'house_share_taka' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return array<string, string> */
    public static function sittingKindOptions(): array
    {
        return [
            ScheduleSession::KIND_VISIT => __('Visit'),
            ScheduleSession::KIND_INTERVENTION => __('Intervention'),
        ];
    }

    public function cashEntries(): HasMany
    {
        return $this->hasMany(ChamberCashEntry::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return array<int|string, string>
     */
    public static function interventionTypeOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->where('sitting_kind', ScheduleSession::KIND_INTERVENTION)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->mapWithKeys(fn (self $item): array => [$item->id => $item->chipLabel()])
            ->all();
    }

    public function chipLabel(): string
    {
        return $this->label.' — ৳'.number_format($this->list_price_taka);
    }
}
