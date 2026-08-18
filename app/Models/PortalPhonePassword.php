<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One password per clinic phone number, used only to open old prescriptions
 * on that clinic's /portal. Serials stay phone-only.
 */
class PortalPhonePassword extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
