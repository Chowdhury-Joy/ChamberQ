<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use BelongsToTenant;
    
    protected $fillable = [
        'name',
        'qualifications',
        'registration_number',
    ];
}
