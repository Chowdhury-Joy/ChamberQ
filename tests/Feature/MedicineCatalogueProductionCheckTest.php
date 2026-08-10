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

        $this->assertGreaterThan(400, Medicine::count());
    }
}
