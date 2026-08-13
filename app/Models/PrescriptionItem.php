<?php

namespace App\Models;

use App\Support\PrescriptionQuantity;
use App\Support\PrescriptionTiming;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'prescription_id',
        'medicine_name',
        'generic_name',
        'indication',
        'dose',
        'frequency',
        'duration',
        'timing',
        'instructions',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function timingLabel(): ?string
    {
        return PrescriptionTiming::displayLabel($this->timing);
    }

    /**
     * Must stay HtmlString. A `?string` return type stringifies the markup,
     * Blade then escapes it, and the patient sees `<span>` on the paper.
     */
    public function timingBilingualLabel(): \Illuminate\Support\HtmlString|string|null
    {
        return PrescriptionTiming::bilingualLabel($this->timing);
    }

    /**
     * Doses this line adds up to, or null when frequency or duration cannot be
     * multiplied out (`SOS`, `Continue`, anything free-typed).
     */
    public function totalDoses(): ?int
    {
        return PrescriptionQuantity::total($this->frequency, $this->duration);
    }
}
