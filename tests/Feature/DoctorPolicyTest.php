<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DoctorPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $soloTenant;
    private Tenant $clinicTenant;
    private User $soloAdmin;
    private User $clinicAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->soloTenant = Tenant::create(['id' => 'solo-doc', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'solo-doc.test', 'tenant_id' => 'solo-doc']);
        $this->soloAdmin = User::create([
            'name' => 'Solo Admin',
            'email' => 'admin@solo-doc.test',
            'password' => bcrypt('password'),
            'role' => 'tenant_admin',
            'tenant_id' => 'solo-doc',
        ]);

        $this->clinicTenant = Tenant::create(['id' => 'clinic-doc', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'clinic-doc.test', 'tenant_id' => 'clinic-doc']);
        $this->clinicAdmin = User::create([
            'name' => 'Clinic Admin',
            'email' => 'admin@clinic-doc.test',
            'password' => bcrypt('password'),
            'role' => 'tenant_admin',
            'tenant_id' => 'clinic-doc',
        ]);
    }

    public function test_solo_tenant_cannot_create_additional_doctor_when_one_exists(): void
    {
        tenancy()->initialize($this->soloTenant);

        Doctor::create(['name' => 'Dr. Solo']);

        $this->assertFalse(Gate::forUser($this->soloAdmin)->allows('create', Doctor::class));

        tenancy()->end();
    }

    public function test_solo_tenant_can_create_first_doctor_when_none_exists(): void
    {
        tenancy()->initialize($this->soloTenant);

        $this->assertTrue(Gate::forUser($this->soloAdmin)->allows('create', Doctor::class));

        tenancy()->end();
    }

    public function test_clinic_tenant_can_always_create_doctors(): void
    {
        tenancy()->initialize($this->clinicTenant);

        Doctor::create(['name' => 'Dr. One']);
        Doctor::create(['name' => 'Dr. Two']);

        $this->assertTrue(Gate::forUser($this->clinicAdmin)->allows('create', Doctor::class));

        tenancy()->end();
    }
}
