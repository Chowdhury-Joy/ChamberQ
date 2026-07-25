<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ScheduleSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'chamber_id',
        'doctor_id',
        'day_of_week',
        'session_name',
        'start_time',
        'end_time',
        'slot_cap',
    ];
    
    public function chamber()
    {
        return $this->belongsTo(Chamber::class);
    }
    
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
