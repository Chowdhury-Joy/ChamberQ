<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;

class User extends Authenticatable implements FilamentUser, CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasFactory, Notifiable, BelongsToTenant;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_DOCTOR = 'doctor';

    public const ROLE_STAFF = 'staff';

    public const ROLE_PATIENT = 'patient';

    /**
     * Tenant panel roles that can sign in.
     *
     * @var list<string>
     */
    public const TENANT_PANEL_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_DOCTOR,
        self::ROLE_STAFF,
    ];

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

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isDoctor(): bool
    {
        return $this->role === self::ROLE_DOCTOR;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /** Schedule, chambers, doctors, slot blocks, labs — not website. */
    public function canManageOps(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_DOCTOR], true);
    }

    /** Edit page text/images; admin also owns page structure. */
    public function canManageContent(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_STAFF], true);
    }

    /** Full page builder: add/remove/reorder blocks, slug, rich HTML. */
    public function canManagePageStructure(): bool
    {
        return $this->isAdmin();
    }

    public function canManageQueue(): bool
    {
        return in_array($this->role, self::TENANT_PANEL_ROLES, true);
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canManageBranding(): bool
    {
        return $this->isAdmin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superAdmin') {
            return $this->role === self::ROLE_SUPER_ADMIN && $this->tenant_id === null;
        }

        if ($panel->getId() === 'tenantAdmin') {
            return in_array($this->role, self::TENANT_PANEL_ROLES, true)
                && $this->tenant_id !== null
                && tenancy()->initialized
                && $this->tenant_id === tenant('id');
        }

        return false;
    }
}
