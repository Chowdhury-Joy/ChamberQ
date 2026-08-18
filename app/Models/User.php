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
use App\Support\ChamberQHelperAccess;
use App\Support\StaffDeskJobs;

class User extends Authenticatable implements FilamentUser, CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasFactory, Notifiable, BelongsToTenant;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    /** Alias: the clinic founder. Stored as `admin` so existing rows stay valid. */
    public const ROLE_OWNER = self::ROLE_ADMIN;

    /** ChamberQ vendor login on a clinic — hidden from the owner. */
    public const ROLE_HELPER = 'helper';

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
        self::ROLE_HELPER,
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
        'assigned_doctor_id',
        'desk_jobs',
        'desk_is_lead',
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
            'desk_jobs' => 'array',
            'desk_is_lead' => 'boolean',
        ];
    }

    /**
     * Deleting a login must not leave a doctor profile pointing at a gone
     * account — there is no FK to do it, because SQLite cannot add one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            ChamberQHelperAccess::guardCreating($user);
        });

        static::updating(function (self $user): void {
            ChamberQHelperAccess::guardUpdating($user);
        });

        static::deleting(function (self $user): void {
            ChamberQHelperAccess::guardDeleting($user);
            Doctor::query()->where('user_id', $user->id)->update(['user_id' => null]);
        });
    }

    /**
     * The prescribing profile this login belongs to, if an admin has paired
     * them. Null for admins, staff, and unpaired doctor accounts.
     */
    public function doctorProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    /** Desk staff assistant lock — null means hospital team. */
    public function assignedDoctor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'assigned_doctor_id');
    }

    /**
     * Branches this login may work at. Empty pivot = all branches (owner,
     * helper, or unscoped staff/doctor).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Chamber, $this>
     */
    public function chambers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Chamber::class, 'chamber_user')
            ->withTimestamps()
            ->withPivot('tenant_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN && $this->tenant_id === null;
    }

    /** Practice setup: clinic founder or ChamberQ helper. */
    public function isAdmin(): bool
    {
        return $this->isOwner() || $this->isHelper();
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isHelper(): bool
    {
        return $this->role === self::ROLE_HELPER;
    }

    public function isDoctor(): bool
    {
        return $this->role === self::ROLE_DOCTOR;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /** Doctors, patients, labs — not website, not chambers/hours, not the front desk lists. */
    public function canManageOps(): bool
    {
        return $this->isAdmin() || $this->isDoctor();
    }

    /** Edit page text/images; owner/helper also own page structure. */
    public function canManageContent(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    /** Full page builder: add/remove/reorder blocks, slug, rich HTML. */
    public function canManagePageStructure(): bool
    {
        return $this->isAdmin();
    }

    /** Desk cashbook — income collected and expenses paid. Owner, helper, doctor, or staff. */
    public function canManageCash(): bool
    {
        return $this->isAdmin() || $this->isDoctor() || $this->isStaff();
    }

    /** Daily roster and queue-adjacent workflows — doctor or staff, not account owner. */
    public function canManageQueue(): bool
    {
        if (! tenant()?->hasFrontDoor()) {
            return false;
        }

        return in_array($this->role, [self::ROLE_DOCTOR, self::ROLE_STAFF], true);
    }

    /**
     * Front-desk lists: today's roster, closed sitting dates, the earlier-date
     * waiting list, follow-up WhatsApp. Staff own these. A doctor only gets
     * them when this practice has no staff login — same idea as the queue
     * fallback, so a solo doctor is not locked out of their own desk.
     * The account owner reaches Daily Roster separately; not follow-up or
     * earlier-date lists.
     */
    public function canWorkDesk(): bool
    {
        if ($this->isStaff()) {
            return true;
        }

        if ($this->isDoctor()) {
            return tenant() !== null && ! tenant()->hasStaffLogin();
        }

        return false;
    }

    /**
     * Chambers and sitting hours. Staff own these; the account owner can
     * still reach them; a doctor only when this practice has no staff login.
     */
    public function canManageSittingSetup(): bool
    {
        return $this->isAdmin() || $this->canWorkDesk();
    }

    /**
     * Day / week / month booking counts — account owner and doctor, not staff.
     */
    public function canViewOperationalReports(): bool
    {
        return $this->canManageOps();
    }

    /**
     * Is this login actually a member of the tenant currently being served?
     *
     * The capability helpers below answer "what may this role do", not "at
     * which practice" — `canAccessPanel()` supplies the second half for
     * Filament pages. Routes registered in `routes/tenant.php` have no panel
     * guard, and all panels share one host and therefore one session cookie,
     * so a doctor signed in to practice A stays authenticated while requesting
     * `/{practiceB}/…`. Any route that serves tenant-owned clinical data must
     * check this as well as the role.
     */
    public function belongsToCurrentTenant(): bool
    {
        return tenancy()->initialized
            && $this->tenant_id !== null
            && $this->tenant_id === tenant('id');
    }

    /** Auto-following consult screen — clinical view for doctors only. */
    public function canViewConsultScreen(): bool
    {
        return $this->isDoctor() && (tenant()?->hasPrescription() ?? false);
    }

    /** Visit notes, prescriptions, and clinical history — doctors only. */
    public function canViewVisitNotes(): bool
    {
        return $this->isDoctor() && (tenant()?->hasPrescription() ?? false);
    }

    /** Recording diagnosis / prescription on complete — doctors only. */
    public function canRecordVisitNotes(): bool
    {
        return $this->isDoctor() && (tenant()?->hasPrescription() ?? false);
    }

    /**
     * Attach scans of paper (reports, handwritten Rx) at the desk.
     *
     * Staff always do this when the practice has a staff login. A doctor only
     * does it when there is nobody at reception — otherwise they stay on the
     * consult pad and do not collect papers themselves.
     */
    public function attachesVisitPaperAtDesk(): bool
    {
        if (! tenant()?->hasPrescription()) {
            return false;
        }

        if ($this->isStaff()) {
            return true;
        }

        return $this->isDoctor() && ! tenant()->hasStaffLogin();
    }

    /**
     * Upload scans from Consult Screen / the Rx pad.
     *
     * Off when staff exist, so the doctor is not photographing papers in the
     * room. Solo doctors keep the pad controls.
     */
    public function attachesVisitPaperOnConsult(): bool
    {
        return $this->canRecordVisitNotes() && ! tenant()->hasStaffLogin();
    }

    /**
     * After login, open Consult Screen instead of the stats dashboard.
     * Doctors with Prescription land there; staff and the account owner stay
     * on the dashboard.
     */
    public function landsOnConsultScreen(): bool
    {
        return $this->isDoctor() && $this->canViewConsultScreen();
    }

    /**
     * Typing up a prescription the doctor wrote on paper.
     *
     * Deliberately separate from `canRecordVisitNotes()`, which stays
     * doctor-only. Staff transcribing a paper script get the medicine list and
     * follow-up and nothing else — no diagnosis, no voice note, no history.
     */
    public function canEnterPrescriptionFor(?Doctor $prescribingDoctor): bool
    {
        if (! tenant()?->hasPrescription()) {
            return false;
        }

        if ($this->canRecordVisitNotes()) {
            return true;
        }

        if (! $this->isStaff()) {
            return false;
        }

        return $prescribingDoctor?->allowsStaffPrescriptionEntry() ?? false;
    }

    /** Live Queue Control — only staff/doctors with the queue desk job. */
    public function canAccessLiveQueueControl(): bool
    {
        if (! tenant()?->hasLiveQueue()) {
            return false;
        }

        return StaffDeskJobs::canRunQueue($this);
    }

    /**
     * Call / complete / skip controls — one party per practice (staff-run or doctor-run).
     */
    public function canOperateQueueControls(): bool
    {
        if (! tenant()?->hasLiveQueue()) {
            return false;
        }

        if (! $this->canManageQueue()) {
            return false;
        }

        $tenant = tenant();
        if (! $tenant) {
            return false;
        }

        // Effective, not configured: a practice whose configured party has no
        // users would otherwise have nobody able to call patients.
        return match ($tenant->effectiveQueueRunner()) {
            Tenant::QUEUE_RUNNER_STAFF => $this->isStaff(),
            Tenant::QUEUE_RUNNER_DOCTOR => $this->isDoctor(),
            default => $this->isStaff(),
        };
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /** Lead desk may hire other Staff only — not owners, doctors, or helpers. */
    public function canManageDeskStaff(): bool
    {
        return $this->canManageUsers() || StaffDeskJobs::isLeadDesk($this);
    }

    public function canViewStaffAndRoles(): bool
    {
        return $this->canManageDeskStaff();
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
