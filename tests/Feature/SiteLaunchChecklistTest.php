<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Resources\Tenants\Pages\EditTenant;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebPage;
use App\Support\SiteLaunchChecklist;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SiteLaunchChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_chamber_fails_google_ready_items(): void
    {
        $tenant = Tenant::create([
            'id' => 'bare-clinic',
            'plan_tier' => 'clinic',
            'name' => 'Bare Clinic',
        ]);

        $items = collect(SiteLaunchChecklist::items($tenant))->keyBy('key');

        $this->assertFalse($items['address']['ok']);
        $this->assertFalse($items['tagline']['ok']);
        $this->assertFalse($items['doctor']['ok']);
        $this->assertFalse($items['homepage']['ok']);
        $this->assertFalse($items['topics']['ok']);
        $this->assertFalse($items['blog']['ok']);
        $this->assertTrue($items['front_door']['ok']);
    }

    public function test_filled_solo_chamber_passes_address_tagline_doctor_and_topics(): void
    {
        $tenant = Tenant::create([
            'id' => 'ready-solo',
            'plan_tier' => 'solo',
            'name' => 'Dr Ready',
            'tagline' => 'Skin clinic in Khulna',
        ]);

        tenancy()->initialize($tenant);
        Chamber::create(['name' => 'Main', 'address' => 'Sonadanga, Khulna']);
        Doctor::create(['name' => 'Dr Ready', 'public_title' => 'Dermatologist']);
        WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [
                [
                    'type' => 'hero',
                    'data' => ['headline' => 'Clear skin, calm visits'],
                ],
                [
                    'type' => 'condition_library',
                    'data' => [
                        'conditions' => [
                            ['name' => 'Acne', 'description' => 'Teen and adult acne.', 'features' => []],
                        ],
                    ],
                ],
            ],
        ]);
        tenancy()->end();

        $items = collect(SiteLaunchChecklist::items($tenant))->keyBy('key');

        $this->assertTrue($items['address']['ok']);
        $this->assertTrue($items['tagline']['ok']);
        $this->assertTrue($items['doctor']['ok']);
        $this->assertTrue($items['homepage']['ok']);
        $this->assertTrue($items['copy']['ok']);
        $this->assertTrue($items['topics']['ok']);
        $this->assertArrayNotHasKey('blog', $items);
    }

    public function test_super_admin_tenant_edit_shows_the_checklist(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'seo-super@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
        ]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('superAdmin'));

        $tenant = Tenant::create([
            'id' => 'checklist-chamber',
            'name' => 'Checklist Chamber',
            'plan_tier' => 'solo',
        ]);

        Livewire::test(EditTenant::class, ['record' => $tenant->getKey()])
            ->assertSuccessful()
            ->assertSee('Google-ready')
            ->assertSee('Chamber address includes a city');
    }
}
