<?php

namespace App\Models;

use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisitRecord extends Model
{
    use BelongsToTenant, HasUuids;

    /**
     * Set on rows loaded from *another* chamber for display only.
     *
     * `CrossTenantClinicalHistoryService` blanks the media paths on those rows
     * before handing them over, and Consult Screen then merges them into the
     * same collection as this chamber's own records. Saving one would write
     * those blanks back and destroy the real voice note and photo paths in the
     * chamber the record actually belongs to — so it is refused outright rather
     * than left to every future caller to notice.
     */
    public bool $isForeignChamberRecord = false;

    public function markAsForeignChamberRecord(): void
    {
        $this->isForeignChamberRecord = true;
    }

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if ($record->isForeignChamberRecord) {
                throw new \RuntimeException(
                    'This visit record belongs to another chamber and was loaded read-only; saving it would erase that chamber’s media paths.'
                );
            }
        });
    }

    protected $fillable = [
        'booking_id',
        'patient_id',
        'recorded_by',
        'condition_id',
        'diagnosis_uncoded',
        'weight_kg',
        'bp_systolic',
        'bp_diastolic',
        'pulse_bpm',
        'spo2_percent',
        'temperature_f',
        'vitals_recorded_by',
        'clinical_notes',
        'chief_complaint',
        'history',
        'on_examination',
        'advice',
        'tests_advised',
        'reports_seen',
        'report_photo_paths',
        'follow_up_date',
        'follow_up_note',
        'follow_up_reminder_sms_sent_at',
        'follow_up_reminder_whatsapp_queued_at',
        'follow_up_reminder_whatsapp_sent_at',
        'voice_path',
        'photo_path',
        'voice_transcript',
        'recorded_at',
        'offline_sync_id',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'bp_systolic' => 'integer',
        'bp_diastolic' => 'integer',
        'pulse_bpm' => 'integer',
        'spo2_percent' => 'integer',
        'temperature_f' => 'float',
        'report_photo_paths' => 'array',
        'follow_up_date' => 'date',
        'follow_up_reminder_sms_sent_at' => 'datetime',
        'follow_up_reminder_whatsapp_queued_at' => 'datetime',
        'follow_up_reminder_whatsapp_sent_at' => 'datetime',
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

    /**
     * The prep-desk login that measured the vitals, when the desk took them.
     *
     * Null means the doctor measured in the chamber (or nobody has yet) — see
     * `vitalsTakenAtDesk()`.
     */
    public function vitalsRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vitals_recorded_by');
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
     * Outdoor desk capture: any usable reading on the row.
     *
     * Drives whether the desk still nags for vitals, so it has to cover every
     * box the desk can fill. When it only knew weight and BP, a staff member
     * who took pulse, SpO₂ and temperature on a patient who refused the scale
     * was still shown "vitals needed" for the rest of the wait.
     *
     * BP counts only as a complete pair — half a blood pressure is not a fact
     * (same rule as `VisitNotesFormSchema::isUsableBloodPressure()`).
     */
    public function hasOutdoorVitals(): bool
    {
        return $this->weight_kg !== null
            || ($this->bp_systolic !== null && $this->bp_diastolic !== null)
            || $this->pulse_bpm !== null
            || $this->spo2_percent !== null
            || $this->temperature_f !== null;
    }

    /**
     * True when the numbers on this row were measured at the prep desk rather
     * than by the doctor. Cleared the moment the doctor changes a reading, so
     * the "taken at the desk" label on the pad can never sit above a number the
     * desk did not take.
     */
    public function vitalsTakenAtDesk(): bool
    {
        return $this->vitals_recorded_by !== null && $this->hasOutdoorVitals();
    }

    /**
     * "78 /min" — or null when pulse was not recorded this visit.
     */
    public function pulseLabel(): ?string
    {
        return $this->pulse_bpm === null ? null : $this->pulse_bpm.' /min';
    }

    /**
     * "98 %" — or null when SpO₂ was not recorded this visit.
     */
    public function spo2Label(): ?string
    {
        return $this->spo2_percent === null ? null : $this->spo2_percent.' %';
    }

    /**
     * "100.5 °F" — or null when temperature was not recorded this visit.
     */
    public function temperatureLabel(): ?string
    {
        if ($this->temperature_f === null) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format((float) $this->temperature_f, 1, '.', ''), '0'), '.');

        return $formatted.' °F';
    }

    /**
     * One line of vitals for the consult screen and summary panel, e.g.
     * "Wt 58.5 kg · BP 170/100 · Pulse 78 /min · SpO₂ 98 % · Temp 100.5 °F". Kept on the model because the same line is
     * drawn in three places and hand-rolled copies had already drifted — one
     * of them showed only blood pressure when both were recorded.
     */
    public function vitalsSummary(): ?string
    {
        $parts = array_filter([
            $this->weightLabel() ? __('Wt').' '.$this->weightLabel() : null,
            $this->bloodPressureLabel() ? __('BP').' '.$this->bloodPressureLabel() : null,
            $this->pulseLabel() ? __('Pulse').' '.$this->pulseLabel() : null,
            $this->spo2Label() ? __('SpO₂').' '.$this->spo2Label() : null,
            $this->temperatureLabel() ? __('Temp').' '.$this->temperatureLabel() : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public function followUpLabel(): ?string
    {
        return VisitNotesFormSchema::followUpDisplayLabel(
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
            || $this->pulse_bpm !== null
            || $this->spo2_percent !== null
            || $this->temperature_f !== null
            || filled($this->clinical_notes)
            || filled($this->chief_complaint)
            || filled($this->history)
            || filled($this->on_examination)
            || filled($this->advice)
            || filled($this->tests_advised)
            || filled($this->reports_seen)
            || filled($this->report_photo_paths)
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
