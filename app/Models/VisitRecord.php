<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisitRecord extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'booking_id',
        'patient_id',
        'recorded_by',
        'condition_id',
        'diagnosis_uncoded',
        'weight_kg',
        'bp_systolic',
        'bp_diastolic',
        'clinical_notes',
        'advice',
        'tests_advised',
        'reports_seen',
        'follow_up_date',
        'follow_up_note',
        'voice_path',
        'photo_path',
        'voice_transcript',
        'recorded_at',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'bp_systolic' => 'integer',
        'bp_diastolic' => 'integer',
        'follow_up_date' => 'date',
        'recorded_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function diagnosisLabel(): ?string
    {
        if ($this->condition) {
            return $this->condition->name;
        }

        return filled($this->diagnosis_uncoded) ? $this->diagnosis_uncoded : null;
    }

    /**
     * "62.5 kg" — or null when weight was not recorded this visit.
     */
    public function weightLabel(): ?string
    {
        if ($this->weight_kg === null) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format((float) $this->weight_kg, 1, '.', ''), '0'), '.');

        return $formatted.' kg';
    }

    /**
     * "170/100" — both numbers required; half-filled rows never reach here.
     */
    public function bloodPressureLabel(): ?string
    {
        if ($this->bp_systolic === null || $this->bp_diastolic === null) {
            return null;
        }

        return $this->bp_systolic.'/'.$this->bp_diastolic;
    }

    /**
     * One line of vitals for the consult screen and summary panel, e.g.
     * "Wt 58.5 kg · BP 170/100". Kept on the model because the same line is
     * drawn in three places and hand-rolled copies had already drifted — one
     * of them showed only blood pressure when both were recorded.
     */
    public function vitalsSummary(): ?string
    {
        $parts = array_filter([
            $this->weightLabel() ? __('Wt').' '.$this->weightLabel() : null,
            $this->bloodPressureLabel() ? __('BP').' '.$this->bloodPressureLabel() : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public function followUpLabel(): ?string
    {
        return \App\Filament\TenantAdmin\Support\VisitNotesFormSchema::followUpDisplayLabel(
            $this->follow_up_date,
            $this->follow_up_note,
        );
    }

    public function hasClinicalContent(): bool
    {
        return filled($this->condition_id)
            || filled($this->diagnosis_uncoded)
            || $this->weight_kg !== null
            || $this->bp_systolic !== null
            || $this->bp_diastolic !== null
            || filled($this->clinical_notes)
            || filled($this->advice)
            || filled($this->tests_advised)
            || filled($this->reports_seen)
            || filled($this->voice_path)
            || filled($this->photo_path)
            || filled($this->voice_transcript)
            || filled($this->follow_up_note)
            || $this->follow_up_date !== null
            || ($this->relationLoaded('prescription')
                ? $this->prescription !== null
                : $this->prescription()->exists());
    }
}
