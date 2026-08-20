<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\BrandingSettings;
use App\Filament\TenantAdmin\Resources\Doctors\DoctorResource;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $soloTenant;
    private Tenant $clinicTenant;
    private User $soloAdmin;
    private User $clinicAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->soloTenant = Tenant::create(['id' => 'brand-solo', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'brand-solo.test', 'tenant_id' => 'brand-solo']);
        $this->soloAdmin = User::create([
            'name' => 'Solo Admin',
            'email' => 'admin@brand-solo.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'tenant_id' => 'brand-solo',
        ]);

        $this->clinicTenant = Tenant::create(['id' => 'brand-clinic', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'brand-clinic.test', 'tenant_id' => 'brand-clinic']);
        $this->clinicAdmin = User::create([
            'name' => 'Clinic Admin',
            'email' => 'admin@brand-clinic.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'tenant_id' => 'brand-clinic',
        ]);
    }

    public function test_sidebar_shows_doctors_menu_for_solo_and_clinic(): void
    {
        tenancy()->initialize($this->soloTenant);
        $this->actingAs($this->soloAdmin);
        $this->assertTrue(DoctorResource::shouldRegisterNavigation());
        tenancy()->end();

        tenancy()->initialize($this->clinicTenant);
        $this->actingAs($this->clinicAdmin);
        $this->assertTrue(DoctorResource::shouldRegisterNavigation());
        tenancy()->end();
    }

    public function test_branding_settings_can_be_saved(): void
    {
        tenancy()->initialize($this->soloTenant);
        $this->actingAs($this->soloAdmin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('tenantAdmin'));

        // Logo and favicon are uploaders now, so a link pasted before that
        // change has to survive being loaded into the picker and saved again.
        $this->soloTenant->update([
            'logo_url' => 'https://example.com/clinic-logo.png',
            'favicon_url' => 'https://example.com/icon.ico',
        ]);

        Livewire::test(BrandingSettings::class)
            ->fillForm([
                'name' => 'Dr. Custom Branding',
                'tagline' => 'Healing with care',
                'theme_color' => '#8b5cf6',
                'font_family' => 'Outfit',
                'contact_phone' => '01700000000',
                'whatsapp_number' => '8801700000000',
                'default_locale' => 'en',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->soloTenant->refresh();

        $this->assertEquals('Dr. Custom Branding', $this->soloTenant->name);
        $this->assertEquals('Healing with care', $this->soloTenant->tagline);
        $this->assertEquals('https://example.com/clinic-logo.png', $this->soloTenant->logo_url);
        $this->assertEquals('https://example.com/icon.ico', $this->soloTenant->favicon_url);
        $this->assertEquals('#8b5cf6', $this->soloTenant->theme_color);
        $this->assertEquals('Outfit', $this->soloTenant->font_family);

        tenancy()->end();
    }

    public function test_owner_can_turn_on_collect_fee_at_checkin(): void
    {
        tenancy()->initialize($this->soloTenant);
        $this->actingAs($this->soloAdmin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('tenantAdmin'));

        $this->assertFalse($this->soloTenant->collectsFeeAtCheckin());

        Livewire::test(BrandingSettings::class)
            ->fillForm([
                'name' => 'Dr. Custom Branding',
                'theme_color' => '#8b5cf6',
                'font_family' => 'Outfit',
                'default_locale' => 'en',
                'collect_fee_at_checkin' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->soloTenant->fresh()->collectsFeeAtCheckin());

        tenancy()->end();
    }

    public function test_owner_can_set_patient_booking_horizon(): void
    {
        tenancy()->initialize($this->soloTenant);
        $this->actingAs($this->soloAdmin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('tenantAdmin'));

        Livewire::test(BrandingSettings::class)
            ->fillForm([
                'name' => 'Dr. Custom Branding',
                'theme_color' => '#8b5cf6',
                'font_family' => 'Outfit',
                'default_locale' => 'en',
                'patient_booking_horizon_days' => 3,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, $this->soloTenant->fresh()->patient_booking_horizon_days);

        tenancy()->end();
    }

    public function test_branding_settings_reject_a_non_hex_theme_color(): void
    {
        tenancy()->initialize($this->soloTenant);
        $this->actingAs($this->soloAdmin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('tenantAdmin'));

        Livewire::test(BrandingSettings::class)
            ->fillForm([
                'name' => 'Dr. Custom Branding',
                'theme_color' => 'QA sweep',
                'font_family' => 'Outfit',
                'default_locale' => 'en',
            ])
            ->call('save')
            ->assertHasFormErrors(['theme_color']);

        $this->assertNotEquals('QA sweep', $this->soloTenant->fresh()->theme_color);

        tenancy()->end();
    }

    public function test_public_booking_page_renders_custom_font_and_color(): void
    {
        $this->soloTenant->update([
            'name' => 'Dr. Custom Branding',
            'theme_color' => '#8b5cf6',
            'font_family' => 'Outfit',
            'logo_url' => 'https://example.com/clinic-logo.png',
        ]);

        $response = $this->get('http://brand-solo.test/book');

        $response->assertStatus(200);
        $response->assertSee('Dr. Custom Branding');
        $response->assertSee('#8b5cf6');
        // Solo booking matches the Figma homepage type system, not the admin font picker.
        $response->assertSee('Instrument Serif', false);
        $response->assertSee('DM Sans', false);
        $response->assertSee('https://example.com/clinic-logo.png');
    }
}
