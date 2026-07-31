<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Relations\HasManyByScheduleAndDate;
use Illuminate\Database\Eloquent\Model;

class LiveSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'schedule_session_id',
        'session_date',
        'status',
        'delay_minutes',
        'current_booking_id',
        'current_called_at',
        'paused_at',
        'pause_reason',
        'estimated_pause_minutes',
        'total_pause_minutes',
        'started_at',
        'completed_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'session_date' => 'date',
        'current_called_at' => 'datetime',
        'paused_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function scheduleSession()
    {
        return $this->belongsTo(ScheduleSession::class);
    }

    public function currentBooking()
    {
        return $this->belongsTo(Booking::class, 'current_booking_id');
    }

    public function bookings()
    {
        $instance = $this->newRelatedInstance(Booking::class);

        return new HasManyByScheduleAndDate(
            $instance->newQuery()->where('bookable_type', ScheduleSession::class),
            $this,
            $instance->getTable().'.bookable_id',
            'schedule_session_id'
        );
    }

    public function skippedBookings()
    {
        return $this->bookings()->where('status', 'skipped')->orderBy('serial_number');
    }

    public function avgConsultationMinutes(): int
    {
        $session = $this->scheduleSession;
        if (!$session) return 30; // fallback
        
        $start = \Carbon\Carbon::parse($session->start_time);
        $end = \Carbon\Carbon::parse($session->end_time);
        $diffInMinutes = $start->diffInMinutes($end);
        
        $cap = $session->slot_cap ?: 1;
        
        return (int) round($diffInMinutes / $cap);
    }

    public function effectiveStartTime(): \Carbon\Carbon
    {
        $start = \Carbon\Carbon::parse($this->scheduleSession->start_time)
            ->setDateFrom($this->session_date);
            
        return $start->addMinutes($this->delay_minutes);
    }

    public function isCallTimedOut(): bool
    {
        if (!$this->current_called_at) return false;
        
        $timeout = $this->tenant?->call_timeout_seconds ?? 10;
        return $this->current_called_at->copy()->addSeconds($timeout)->isPast();
    }
}
