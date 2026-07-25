<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SlotBlock extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'chamber_id',
        'doctor_id',
        'date',
        'reason',
    ];
    
    public function chamber()
    {
        return $this->belongsTo(Chamber::class);
    }
    
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
