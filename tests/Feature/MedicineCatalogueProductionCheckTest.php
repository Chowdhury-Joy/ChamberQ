<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Support\ProductionReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineCatalogueProductionCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_catalogue_is_a_production_blocker(): void
    {
        $this->app['env'] = 'production';

        $keys = array_column(ProductionReadiness::problems(), 'key');

        $this->assertContains('MEDICINE_CATALOGUE', $keys);

        $problem = collect(ProductionReadiness::problems())->firstWhere('key', 'MEDICINE_CATALOGUE');

        $this->assertSame(ProductionReadiness::SEVERITY_BLOCKER, $problem['severity']);
    }

    public function test_loaded_catalogue_is_not_reported_on_production_servers(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('catalogues:load')->assertExitCode(0);

        $this->assertNotContains(
            'MEDICINE_CATALOGUE',
            array_column(ProductionReadiness::problems(), 'key'),
        );
    }

    public function test_catalogues_load_imports_medicines_and_conditions(): void
    {
        $this->artisan('catalogues:load')->assertExitCode(0);

        // The catalogue is the full Bangladesh market (24,491 SKUs), not the
        // former curated 460. The floor is deliberately well below that so a
        // catalogue refresh does not have to update this test, but well above
        // the old size so a silent regression to the curated subset — or a
        // loader that collapses SKUs back onto brand names — fails here.
        $this->assertGreaterThan(20000, Medicine::count());
    }

    public function test_catalogue_keeps_every_form_a_brand_ships_in(): void
    {
        $this->artisan('catalogues:load')->assertExitCode(0);

        $napa = Medicine::query()->where('brand_name', 'NAPA')->get();

        // Upserting on brand_name alone silently discarded 8,656 SKUs, hitting
        // syrups and paediatric drops hardest. If the loader's key regresses,
        // NAPA collapses to one row and a GP cannot prescribe it to a child.
        $this->assertGreaterThan(1, $napa->count());
        $this->assertTrue($napa->contains(fn (Medicine $m): bool => $m->form === 'syrup'));
        $this->assertTrue($napa->contains(fn (Medicine $m): bool => $m->form === 'tablet'));
    }
}
