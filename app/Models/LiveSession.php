<?php

namespace App\Models;

use App\Casts\DateOnly;
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
        'session_date' => DateOnly::class,
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

    /**
     * When the queue clock effectively began for ETA purposes.
     *
     * - Not started + delayed: sitting start + announced delay (yellow still on).
     * - Started: later of sitting start and started_at (+ pause) — yellow off.
     * - Scheduled, not started: sitting start.
     *
     * `delay_minutes` is kept as "what we told patients" and is not zeroed on Start.
     */
    public function effectiveStartTime(): \Carbon\Carbon
    {
        $sittingStart = \Carbon\Carbon::parse($this->scheduleSession->start_time)
            ->setDateFrom($this->session_date);

        if ($this->status === 'delayed' && ! $this->started_at) {
            return $sittingStart->copy()->addMinutes((int) $this->delay_minutes);
        }

        $totalPause = (int) $this->total_pause_minutes;
        if ($this->status === 'paused') {
            $totalPause += (int) ($this->estimated_pause_minutes ?: 0);
        }

        if ($this->started_at) {
            $effective = $sittingStart->max($this->started_at);

            return $effective->copy()->addMinutes($totalPause);
        }

        return $sittingStart->copy()->addMinutes($totalPause);
    }

    public function callTimeoutSeconds(): int
    {
        return (int) ($this->tenant?->call_timeout_seconds ?? 10);
    }

    public function isCallTimedOut(): bool
    {
        if (!$this->current_called_at) return false;

        return $this->current_called_at->copy()->addSeconds($this->callTimeoutSeconds())->isPast();
    }

    /**
     * When a paused session is expected back, so staff can tell waiting
     * patients a time instead of "soon". Null when not paused or no estimate.
     */
    public function pauseEndsAt(): ?\Carbon\Carbon
    {
        if ($this->status !== 'paused' || !$this->paused_at) return null;

        $minutes = (int) ($this->estimated_pause_minutes ?: 0);
        if ($minutes < 1) return null;

        return $this->paused_at->copy()->addMinutes($minutes);
    }
}
