<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionTemplateItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'prescription_template_id',
        'medicine_name',
        'generic_name',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(PrescriptionTemplate::class, 'prescription_template_id');
    }
}
