<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TieredFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_tier_defaults_are_correct()
    {
        $tenant = Tenant::create(['id' => 'solo-tenant', 'plan_tier' => 'solo']);
        
        $this->assertFalse($tenant->hasFeature('lab_tests'));
        $this->assertFalse($tenant->hasFeature('multiple_chambers'));
        $this->assertFalse($tenant->hasFeature('multiple_doctors'));
    }

    public function test_clinic_tier_defaults_are_correct()
    {
        $tenant = Tenant::create(['id' => 'clinic-tenant', 'plan_tier' => 'clinic']);
        
        $this->assertTrue($tenant->hasFeature('lab_tests'));
        $this->assertTrue($tenant->hasFeature('multiple_chambers'));
        $this->assertTrue($tenant->hasFeature('multiple_doctors'));
    }

    public function test_feature_flags_override_tier_defaults()
    {
        $tenant = Tenant::create([
            'id' => 'upgraded-solo',
            'plan_tier' => 'solo',
            'feature_flags' => [
                'lab_tests' => true
            ]
        ]);
        
        // Overridden
        $this->assertTrue($tenant->hasFeature('lab_tests'));
        // Still defaults
        $this->assertFalse($tenant->hasFeature('multiple_chambers'));
    }
}
