<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleSessionOverride extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'schedule_session_id',
        'override_date',
        'start_time',
        'end_time',
        'slot_cap',
        'walk_in_overflow_cap',
    ];

    protected $casts = [
        'override_date' => DateOnly::class,
        'slot_cap' => 'integer',
        'walk_in_overflow_cap' => 'integer',
    ];

    public function scheduleSession(): BelongsTo
    {
        return $this->belongsTo(ScheduleSession::class);
    }
}
