<?php

namespace App\Models;

use App\Support\BdPhone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Platform patient login. Keyed by phone — not a clinical file.
 *
 * Per-clinic `patients` rows stay tenant-scoped. This account is the locker
 * key that finds them.
 */
class PatientAccount extends Authenticatable
{
    use HasUuids;

    protected $fillable = [
        'phone',
        'name',
        'nid',
        'last_login_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function normalizedPhone(): string
    {
        return BdPhone::normalize((string) $this->phone);
    }
}
