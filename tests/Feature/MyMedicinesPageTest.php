<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\MyMedicines;
use App\Models\Domain;
use App\Models\MedicineUsage;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MyMedicinesPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'my-medicines', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'my-medicines.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Mine',
            'email' => 'doc@mine.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        MedicineUsage::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->doctor->id,
            'medicine_name' => 'NAPA',
            'generic_name' => 'Paracetamol',
            'last_dose' => '500 mg',
            'last_frequency' => '1+1+1',
            'last_duration' => '5 days',
            'use_count' => 3,
            'last_used_at' => now(),
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_doctor_can_edit_personal_medicine_defaults(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $usage = MedicineUsage::query()->where('user_id', $this->doctor->id)->first();

        Livewire::test(MyMedicines::class)
            ->assertSee('NAPA')
            ->callTableAction('edit', $usage, data: [
                'medicine_name' => 'NAPA EXTRA',
                'generic_name' => 'Paracetamol',
                'last_dose' => '500 mg',
                'last_frequency' => '1+0+1',
                'last_duration' => '7 days',
            ]);

        $usage->refresh();

        $this->assertSame('NAPA EXTRA', $usage->medicine_name);
        $this->assertSame('1+0+1', $usage->last_frequency);
        $this->assertSame('7 days', $usage->last_duration);
    }

    public function test_doctor_can_hide_a_medicine_from_their_list(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $usage = MedicineUsage::query()->where('user_id', $this->doctor->id)->first();

        Livewire::test(MyMedicines::class)
            ->callTableAction('hide', $usage);

        $this->assertNotNull($usage->fresh()->hidden_at);
    }
}
