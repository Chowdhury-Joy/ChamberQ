<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LabTest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'preparation_instructions',
        'sample_type',
        'price',
        'turnaround_time',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $test) {
            if (blank($test->slug)) {
                $test->slug = Str::slug($test->name) ?: Str::random(8);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class)
            ->using(BookingLabTest::class)
            ->withPivot('price_at_booking')
            ->withTimestamps();
    }
}
