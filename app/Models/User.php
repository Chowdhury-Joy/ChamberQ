<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Models\Concerns\BelongsToTenant;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superAdmin') {
            return $this->role === 'super_admin' && $this->tenant_id === null;
        }

        if ($panel->getId() === 'tenantAdmin') {
            // The tenant_id must match the tenant whose subdomain is being
            // served. Checking only that *a* tenant is set would let any tenant
            // admin open any other tenant's panel whenever the session cookie is
            // shared across subdomains (which wildcard SSL setups commonly do).
            return $this->role === 'tenant_admin'
                && $this->tenant_id !== null
                && tenancy()->initialized
                && $this->tenant_id === tenant('id');
        }

        return false;
    }
}
