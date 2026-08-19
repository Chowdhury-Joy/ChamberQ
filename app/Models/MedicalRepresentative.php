<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalRepresentative extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'company',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function displayLabel(): string
    {
        $company = trim((string) $this->company);

        return $company !== ''
            ? $this->name.' ('.$company.')'
            : (string) $this->name;
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
