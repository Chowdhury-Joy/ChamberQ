<?php

namespace App\Models;

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

    public function timingBilingualLabel(): ?string
    {
        return PrescriptionTiming::bilingualLabel($this->timing);
    }
}
