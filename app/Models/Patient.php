<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Patient extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'name',
        'phone',
        'nid',
        'date_of_birth',
        'age',
        'age_recorded_at',
        'sex',
        'allergies',
        'conditions',
        'medicines',
        'share_clinical_history',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'age_recorded_at' => 'date',
        'age' => 'integer',
        'share_clinical_history' => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function visits(): HasMany
    {
        return $this->bookings();
    }

    public function getVisitCountAttribute(): int
    {
        return $this->bookings()
            ->where('status', 'completed')
            ->count();
    }

    public function getLastVisitAtAttribute(): ?\Illuminate\Support\Carbon
    {
        $date = $this->bookings()
            ->where('status', 'completed')
            ->max('booking_date');

        return $date ? \Illuminate\Support\Carbon::parse($date) : null;
    }

    /**
     * Short label for booking picker: "Fatima Begum, 34" or just the name.
     *
     * Staff-side only (Daily Roster / Live Queue walk-in modals), where the
     * user is authenticated and authorised to see the full patient list. The
     * public booking wizard must use `maskedPickerLabel()` instead.
     */
    public function pickerLabel(): string
    {
        $age = $this->displayAge();

        return $age !== null
            ? "{$this->name}, {$age}"
            : $this->name;
    }

    /**
     * Household-picker label for the **public**, unauthenticated wizard:
     * "F. B., 34".
     *
     * `/api/patients/by-phone` has to stay open — the wizard calls it before
     * anyone has logged in — so anyone who types a valid BD mobile gets a
     * reply. Returning full names there let a script walk the number range and
     * rebuild a clinic's patient list with names and visit counts. Initials
     * plus age still let a real household recognise its own members (they know
     * who is on their own phone) while being close to useless in bulk.
     */
    public function maskedPickerLabel(): string
    {
        $initials = collect(preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)).'.')
            ->implode(' ');

        if ($initials === '') {
            $initials = '—';
        }

        $age = $this->displayAge();

        return $age !== null ? "{$initials}, {$age}" : $initials;
    }

    public function displayAge(): ?int
    {
        if ($this->date_of_birth) {
            return (int) $this->date_of_birth->diffInYears(now());
        }

        if ($this->age !== null && $this->age_recorded_at) {
            $yearsSince = (int) $this->age_recorded_at->diffInYears(now());

            return $this->age + $yearsSince;
        }

        return $this->age;
    }

    public function displaySex(): ?string
    {
        return filled($this->sex) ? (string) $this->sex : null;
    }

    public function completedVisitCount(): int
    {
        return $this->bookings()
            ->where('status', 'completed')
            ->count();
    }

    /**
     * @return 'first_time'|'visits_no_notes'|'visits_with_notes'
     */
    public function consultHistoryState(): string
    {
        $completed = $this->completedVisitCount();

        if ($completed === 0) {
            return 'first_time';
        }

        if (app(\App\Services\VisitRecordService::class)->patientHasRecordedNotes($this)) {
            return 'visits_with_notes';
        }

        return 'visits_no_notes';
    }

    public function consultHistoryLabel(): string
    {
        return match ($this->consultHistoryState()) {
            'first_time' => __('First visit — no history'),
            'visits_no_notes' => __(':count previous visits · no notes recorded', [
                'count' => $this->completedVisitCount(),
            ]),
            'visits_with_notes' => __(':count previous visits', [
                'count' => $this->completedVisitCount(),
            ]),
        };
    }

    public function lastSeenLabel(): ?string
    {
        $last = $this->last_visit_at;

        if (! $last) {
            return null;
        }

        return $last->diffForHumans(now(), [
            'parts' => 1,
            'short' => false,
            'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
        ]);
    }

    public function hasClinicalWarnings(): bool
    {
        return filled($this->allergies)
            || filled($this->conditions)
            || filled($this->medicines);
    }
}
