<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Resources\Chambers\Pages\CreateChamber;
use App\Models\Chamber;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChamberMapLinkTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'map-link', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'map-link.localhost', 'tenant_id' => 'map-link']);
        tenancy()->initialize($this->tenant);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@map-link.loc',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'tenant_id' => 'map-link',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_admin_can_save_a_chamber_with_a_pasted_google_maps_link(): void
    {
        Livewire::test(CreateChamber::class)
            ->fillForm([
                'name' => 'Dhanmondi Chamber',
                'address' => 'House 42, Road 9/A, Dhanmondi',
                'map_url' => 'https://maps.app.goo.gl/aBcDeF123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'https://maps.app.goo.gl/aBcDeF123',
            Chamber::where('name', 'Dhanmondi Chamber')->first()->googleMapsUrl()
        );
    }

    public function test_admin_cannot_save_a_link_that_is_not_google_maps(): void
    {
        Livewire::test(CreateChamber::class)
            ->fillForm([
                'name' => 'Bad Link Chamber',
                'map_url' => 'https://evil.example/maps?q=1,2',
            ])
            ->call('create')
            ->assertHasFormErrors(['map_url']);

        $this->assertNull(Chamber::where('name', 'Bad Link Chamber')->first());
    }

    public function test_map_link_is_optional_and_falls_back_to_the_address(): void
    {
        Livewire::test(CreateChamber::class)
            ->fillForm([
                'name' => 'No Link Chamber',
                'address' => 'Plot 7, Mirpur 10, Dhaka',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Plot 7, Mirpur 10, Dhaka'),
            Chamber::where('name', 'No Link Chamber')->first()->googleMapsUrl()
        );
    }

    /**
     * @dataProvider googleMapsLinkProvider
     */
    public function test_recognises_google_maps_link_shapes(string $url, bool $expected): void
    {
        $this->assertSame($expected, Chamber::isGoogleMapsUrl($url));
    }

    public static function googleMapsLinkProvider(): array
    {
        return [
            'mobile share link' => ['https://maps.app.goo.gl/aBcDeF123', true],
            'desktop maps path' => ['https://www.google.com/maps/place/Dhaka', true],
            'maps subdomain' => ['https://maps.google.com/?q=23.7,90.3', true],
            'country domain' => ['https://www.google.com.bd/maps?q=23.7,90.3', true],
            'google but not maps' => ['https://www.google.com/search?q=dhaka', false],
            'lookalike host' => ['https://google.com.evil.example/maps', false],
            'javascript scheme' => ['javascript:alert(1)', false],
            'empty' => ['', false],
        ];
    }
}
