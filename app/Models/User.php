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

    public const ROLE_MARKETER = 'marketer';

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

    /** Daily roster and queue-adjacent workflows — doctor or staff, not account owner. */
    public function canManageQueue(): bool
    {
        return in_array($this->role, [self::ROLE_DOCTOR, self::ROLE_STAFF], true);
    }

    /** Auto-following consult screen — clinical view for doctors only. */
    public function canViewConsultScreen(): bool
    {
        return $this->isDoctor();
    }

    /** Visit notes, prescriptions, and clinical history — doctors only. */
    public function canViewVisitNotes(): bool
    {
        return $this->isDoctor();
    }

    /** Recording diagnosis / prescription on complete — doctors only. */
    public function canRecordVisitNotes(): bool
    {
        return $this->isDoctor();
    }

    /** Live Queue Control — only the party configured to run the queue. */
    public function canAccessLiveQueueControl(): bool
    {
        return $this->canOperateQueueControls();
    }

    /**
     * Call / complete / skip controls — one party per practice (staff-run or doctor-run).
     */
    public function canOperateQueueControls(): bool
    {
        if (! $this->canManageQueue()) {
            return false;
        }

        $tenant = tenant();
        if (! $tenant) {
            return false;
        }

        return match ($tenant->queueRunner()) {
            Tenant::QUEUE_RUNNER_STAFF => $this->isStaff(),
            Tenant::QUEUE_RUNNER_DOCTOR => $this->isDoctor(),
            default => $this->isStaff(),
        };
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canManageBranding(): bool
    {
        return $this->isAdmin();
    }

    public function isMarketer(): bool
    {
        return $this->role === self::ROLE_MARKETER;
    }

    public function marketerProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Marketer::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superAdmin') {
            return $this->role === self::ROLE_SUPER_ADMIN && $this->tenant_id === null;
        }

        if ($panel->getId() === 'marketer') {
            return $this->role === self::ROLE_MARKETER
                && $this->tenant_id === null
                && $this->marketerProfile()->exists();
        }

        if (in_array($panel->getId(), ['tenantAdmin', 'tenantAdminPath'], true)) {
            return in_array($this->role, self::TENANT_PANEL_ROLES, true)
                && $this->tenant_id !== null
                && tenancy()->initialized
                && $this->tenant_id === tenant('id');
        }

        return false;
    }
}
