<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ScheduleSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'chamber_id',
        'doctor_id',
        'day_of_week',
        'session_name',
        'start_time',
        'end_time',
        'slot_cap',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];
    
    public function chamber()
    {
        return $this->belongsTo(Chamber::class);
    }
    
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /** Label for outdoor screen: session name + time window. */
    public function screenLabel(): string
    {
        $start = \Carbon\Carbon::parse($this->start_time)->format('g:i A');
        $end = \Carbon\Carbon::parse($this->end_time)->format('g:i A');

        return trim($this->session_name).' · '.$start.' – '.$end;
    }
}
