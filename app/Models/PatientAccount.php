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

    /**
     * `nid` is deliberately NOT fillable.
     *
     * `PlatformPatientHistoryService` treats a matching NID as proof that this
     * account owns a `patients` row — in `assertOwnsPrescription()` and in
     * `matchingPatientIds()`. An NID this account could set for itself would
     * therefore be a self-issued key to any patient record carrying that
     * number, at any chamber. Only a chamber that has seen the card may write
     * one, and nothing does today.
     */
    protected $fillable = [
        'phone',
        'name',
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
