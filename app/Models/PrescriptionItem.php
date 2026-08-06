<?php

namespace App\Models;

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
        'dose',
        'frequency',
        'duration',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
