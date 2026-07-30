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
        $this->assertTrue($tenant->hasFeature('multiple_chambers'));
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
        // Still solo defaults
        $this->assertTrue($tenant->hasFeature('multiple_chambers'));
    }

    public function test_string_false_feature_flag_is_treated_as_disabled(): void
    {
        $tenant = Tenant::create([
            'id' => 'string-false-solo',
            'plan_tier' => 'solo',
            'feature_flags' => [
                'lab_tests' => 'false',
            ],
        ]);

        $this->assertFalse($tenant->hasFeature('lab_tests'));

        $clinic = Tenant::create([
            'id' => 'string-false-clinic',
            'plan_tier' => 'clinic',
            'feature_flags' => [
                'lab_tests' => 'false',
                'multiple_doctors' => '0',
            ],
        ]);

        $this->assertFalse($clinic->hasFeature('lab_tests'));
        $this->assertFalse($clinic->hasFeature('multiple_doctors'));
        $this->assertTrue($clinic->hasFeature('multiple_chambers'));
    }

    public function test_string_true_feature_flag_enables_override(): void
    {
        $tenant = Tenant::create([
            'id' => 'string-true-solo',
            'plan_tier' => 'solo',
            'feature_flags' => [
                'lab_tests' => 'true',
            ],
        ]);

        $this->assertTrue($tenant->hasFeature('lab_tests'));
    }

    public function test_bangla_homepage_is_off_by_default_and_flaggable(): void
    {
        $solo = Tenant::create(['id' => 'bn-home-solo', 'plan_tier' => 'solo']);
        $this->assertFalse($solo->hasFeature('bangla_homepage'));

        $solo->update(['feature_flags' => ['bangla_homepage' => true]]);
        $this->assertTrue($solo->fresh()->hasFeature('bangla_homepage'));
    }

    public function test_solo_max_chambers_is_five_and_clinic_is_unlimited(): void
    {
        $solo = Tenant::create(['id' => 'max-solo', 'plan_tier' => 'solo']);
        $clinic = Tenant::create(['id' => 'max-clinic', 'plan_tier' => 'clinic']);

        $this->assertSame(5, $solo->maxChambers());
        $this->assertNull($clinic->maxChambers());

        $solo->update(['feature_flags' => ['multiple_chambers' => false]]);
        $this->assertSame(1, $solo->fresh()->maxChambers());
    }
}
