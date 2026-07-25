<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class TenantLeakageTest extends TestCase
{
    use RefreshDatabase;

    public static function scopedModelsProvider(): array
    {
        return [
            'Doctor' => [\App\Models\Doctor::class, ['name' => 'Test Doctor']],
            'Chamber' => [\App\Models\Chamber::class, ['name' => 'Test Chamber']],
            'ScheduleSession' => [\App\Models\ScheduleSession::class, ['session_name' => 'Morning', 'start_time' => '09:00', 'end_time' => '13:00', 'slot_cap' => 10, 'day_of_week' => 1, 'chamber_id' => 1, 'doctor_id' => 1]],
            'LabTest' => [\App\Models\LabTest::class, ['name' => 'Blood Test', 'price' => 100]],
            'LabCollectionSlot' => [\App\Models\LabCollectionSlot::class, ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '10:00', 'slot_cap' => 30]],
            'Booking' => [\App\Models\Booking::class, ['booking_date' => '2026-07-25', 'patient_name' => 'John', 'patient_phone' => '01712345678', 'serial_number' => 1, 'status' => 'waiting', 'payment_status' => 'unpaid', 'bookable_type' => \App\Models\Doctor::class, 'bookable_id' => 1]],
            'SlotBlock' => [\App\Models\SlotBlock::class, ['date' => '2026-07-25']],
            'WebPage' => [\App\Models\WebPage::class, ['title' => 'Home', 'slug' => 'home']],
            'User' => [\App\Models\User::class, ['name' => 'Admin', 'email' => 'admin_test@test.com', 'password' => 'password']],
        ];
    }

    /**
     * @dataProvider scopedModelsProvider
     */
    public function test_tenant_cannot_see_other_tenants_data(string $modelClass, array $factoryData)
    {
        // Seed two tenants
        $tenant1 = Tenant::create(['id' => 'tenant1']);
        $tenant2 = Tenant::create(['id' => 'tenant2']);

        // Prevent FK constraint failures for models that require relations
        Schema::disableForeignKeyConstraints();

        // Create record for tenant1
        tenancy()->initialize($tenant1);
        
        if (isset($factoryData['chamber_id'])) {
            $chamber = \App\Models\Chamber::create(['name' => 'Test Chamber']);
            $factoryData['chamber_id'] = $chamber->id;
        }
        if (isset($factoryData['doctor_id'])) {
            $doctor = \App\Models\Doctor::create(['name' => 'Test Doc']);
            $factoryData['doctor_id'] = $doctor->id;
        }
        
        $record1 = $modelClass::create($factoryData);
        
        $this->assertEquals(1, $modelClass::count());
        $this->assertNotNull($modelClass::find($record1->id));
        $this->assertEquals('tenant1', $record1->tenant_id);

        // Create record for tenant2
        tenancy()->initialize($tenant2);
        
        if (isset($factoryData['chamber_id'])) {
            $chamber = \App\Models\Chamber::create(['name' => 'Test Chamber 2']);
            $factoryData['chamber_id'] = $chamber->id;
        }
        if (isset($factoryData['doctor_id'])) {
            $doctor = \App\Models\Doctor::create(['name' => 'Test Doc 2']);
            $factoryData['doctor_id'] = $doctor->id;
        }
        
        $record2 = $modelClass::create($factoryData);

        $this->assertEquals(1, $modelClass::count()); // Sees only their own
        $this->assertNotNull($modelClass::find($record2->id));
        $this->assertNull($modelClass::find($record1->id)); // Cannot see tenant 1's record by primary key
        $this->assertEquals('tenant2', $record2->tenant_id);

        // Back to tenant 1
        tenancy()->initialize($tenant1);
        $this->assertEquals(1, $modelClass::count());
        $this->assertNotNull($modelClass::find($record1->id));
        $this->assertNull($modelClass::find($record2->id)); // Cannot see tenant 2's record by primary key

        Schema::enableForeignKeyConstraints();
    }
}

