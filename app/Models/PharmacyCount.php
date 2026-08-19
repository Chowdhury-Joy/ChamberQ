<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacyCount extends Model
{
    use BelongsToTenant;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SAVED = 'saved';

    protected $fillable = [
        'status',
        'started_by',
        'saved_by',
        'saved_at',
    ];

    protected $casts = [
        'saved_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PharmacyCountItem::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
