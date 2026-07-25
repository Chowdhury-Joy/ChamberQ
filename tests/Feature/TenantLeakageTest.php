<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantLeakageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_cannot_see_other_tenants_data()
    {
        // Seed two tenants
        $tenant1 = Tenant::create(['id' => 'tenant1']);
        $tenant2 = Tenant::create(['id' => 'tenant2']);

        // Create a doctor for tenant1
        tenancy()->initialize($tenant1);
        Doctor::create(['name' => 'Doctor 1']);
        
        $this->assertEquals(1, Doctor::count());
        $this->assertEquals('Doctor 1', Doctor::first()->name);

        // Create a doctor for tenant2
        tenancy()->initialize($tenant2);
        Doctor::create(['name' => 'Doctor 2']);

        $this->assertEquals(1, Doctor::count()); // Sees only their own
        $this->assertEquals('Doctor 2', Doctor::first()->name);

        // Back to tenant 1
        tenancy()->initialize($tenant1);
        $this->assertEquals(1, Doctor::count());
        $this->assertEquals('Doctor 1', Doctor::first()->name);
    }
}
