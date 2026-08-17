<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ScheduleSession extends Model
{
    use BelongsToTenant;

    public const KIND_CONSULT = 'consult';

    public const KIND_VISIT = 'visit';

    public const KIND_INTERVENTION = 'intervention';

    public const KIND_COUNSELING = 'counseling';

    protected $fillable = [
        'chamber_id',
        'doctor_id',
        'day_of_week',
        'session_name',
        'kind',
        'start_time',
        'end_time',
        'slot_cap',
        'walk_in_overflow_cap',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'walk_in_overflow_cap' => 'integer',
    ];
    
    public function chamber()
    {
        return $this->belongsTo(Chamber::class);
    }
    
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /** @return array<string, string> */
    public static function kindOptions(): array
    {
        return [
            self::KIND_CONSULT => __('Consult (free)'),
            self::KIND_VISIT => __('Visit'),
            self::KIND_INTERVENTION => __('Intervention'),
            self::KIND_COUNSELING => __('Counseling (free)'),
        ];
    }

    public function kindLabel(): ?string
    {
        if (! filled($this->kind)) {
            return null;
        }

        return self::kindOptions()[$this->kind] ?? $this->kind;
    }

    public function isConsultKind(): bool
    {
        return $this->kind === self::KIND_CONSULT;
    }

    public function isInterventionKind(): bool
    {
        return $this->kind === self::KIND_INTERVENTION;
    }

    public function isFreeKind(): bool
    {
        return in_array($this->kind, [self::KIND_CONSULT, self::KIND_COUNSELING], true);
    }

    /**
     * Public wizard + POST /api/bookings. Intervention and counseling are
     * staff-pushed only. Legacy rows with no kind stay bookable.
     */
    public function isPubliclyBookable(): bool
    {
        if (! filled($this->kind)) {
            return true;
        }

        return in_array($this->kind, [self::KIND_VISIT, self::KIND_CONSULT], true);
    }

    /** Label for outdoor screen: session name + time window. */
    public function screenLabel(): string
    {
        $start = \Carbon\Carbon::parse($this->start_time)->format('g:i A');
        $end = \Carbon\Carbon::parse($this->end_time)->format('g:i A');
        $label = trim($this->session_name);

        if (tenant()?->hasStations() && filled($this->kind)) {
            $label .= ' · '.($this->kindLabel() ?? $this->kind);
        }

        return $label.' · '.$start.' – '.$end;
    }

    public function overrides()
    {
        return $this->hasMany(ScheduleSessionOverride::class);
    }

    public function minutesPerPatient(): ?int
    {
        return \App\Support\ScheduleSessionPace::minutesPerPatient($this);
    }

    public function publishedSlotCap(): int
    {
        return \App\Support\ScheduleSessionPace::publishedCap($this);
    }
}
