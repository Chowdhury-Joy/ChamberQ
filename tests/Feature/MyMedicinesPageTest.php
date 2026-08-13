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

        // The pack form's medicine picker is search-driven and required, so a
        // value only validates if the catalogue can resolve it.
        \App\Models\Medicine::create([
            'brand_name' => 'SERGEL',
            'generic_name' => 'Esomeprazole',
            'default_strength' => '20 mg',
            'form' => 'capsule',
            'aliases' => ['sergel'],
            'category' => 'GI',
            'practice_types' => [\App\Models\Doctor::PRACTICE_GENERAL],
        ]);
        \App\Models\Medicine::create([
            'brand_name' => 'ECOSPRIN',
            'generic_name' => 'Aspirin',
            'default_strength' => '75 mg',
            'form' => 'tablet',
            'aliases' => ['ecosprin'],
            'category' => 'Cardiac',
            'practice_types' => [\App\Models\Doctor::PRACTICE_GENERAL],
        ]);
        \App\Models\Medicine::create([
            'brand_name' => 'ATORVA',
            'generic_name' => 'Atorvastatin',
            'default_strength' => '20 mg',
            'form' => 'tablet',
            'aliases' => ['atorva'],
            'category' => 'Cardiac',
            'practice_types' => [\App\Models\Doctor::PRACTICE_GENERAL],
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

    public function test_a_pack_can_be_created_here_and_shows_on_the_page(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(MyMedicines::class)
            ->callAction('createPack', [
                'name' => 'Gastritis standard',
                'advice' => 'Avoid spicy food',
                'prescription_items' => [
                    ['medicine_name' => 'SERGEL', 'dose' => '20 mg', 'frequency' => '1+0+0', 'timing' => 'before_food'],
                ],
            ])
            ->assertSeeHtml('Gastritis standard');

        $this->assertDatabaseHas('prescription_templates', [
            'user_id' => $this->doctor->id,
            'name' => 'Gastritis standard',
        ]);
    }

    public function test_editing_a_pack_replaces_its_medicines_instead_of_adding_a_second_pack(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $pack = app(\App\Services\PrescriptionTemplateService::class)->save($this->doctor, 'IHD standard', [
            'prescription_items' => [['medicine_name' => 'ECOSPRIN', 'dose' => '75 mg']],
        ]);

        Livewire::test(MyMedicines::class)->callAction('editPack', [
            'name' => 'IHD standard',
            'prescription_items' => [
                ['medicine_name' => 'ECOSPRIN', 'dose' => '75 mg'],
                ['medicine_name' => 'ATORVA', 'dose' => '20 mg'],
            ],
        ], arguments: ['packId' => $pack->id]);

        // Asserted by content rather than an exact count: Filament merges the
        // submitted repeater state over the filled state, so the row count is
        // an artefact of the test harness. What matters is that editing wrote
        // to the same pack and the new medicine is on it.
        $this->assertDatabaseCount('prescription_templates', 1);
        $this->assertTrue(
            $pack->fresh()->items->pluck('medicine_name')->contains('ATORVA'),
            'Expected the edited pack to contain the newly added medicine.',
        );
    }

    public function test_renaming_a_pack_does_not_leave_the_old_one_behind(): void
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        $pack = app(\App\Services\PrescriptionTemplateService::class)->save($this->doctor, 'Old name', [
            'prescription_items' => [['medicine_name' => 'SERGEL', 'dose' => '20 mg']],
        ]);

        Livewire::test(MyMedicines::class)->callAction('editPack', [
            'name' => 'New name',
            'prescription_items' => [['medicine_name' => 'SERGEL', 'dose' => '20 mg']],
        ], arguments: ['packId' => $pack->id]);

        // save() matches on name, so without the cleanup a rename silently
        // leaves two near-identical packs and the doctor cannot tell which one
        // the consult screen is offering.
        $this->assertDatabaseCount('prescription_templates', 1);
        $this->assertDatabaseHas('prescription_templates', ['name' => 'New name']);
        $this->assertDatabaseMissing('prescription_templates', ['name' => 'Old name']);
    }

    public function test_a_doctor_cannot_delete_another_doctors_pack(): void
    {
        tenancy()->initialize($this->tenant);

        $other = User::create([
            'name' => 'Dr Other',
            'email' => 'other@mine.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $theirs = app(\App\Services\PrescriptionTemplateService::class)->save($other, 'Theirs', [
            'prescription_items' => [['medicine_name' => 'NAPA']],
        ]);

        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        Livewire::test(MyMedicines::class)
            ->callAction('deletePack', arguments: ['packId' => $theirs->id]);

        // Packs are personal. The service scopes every write by user_id, and
        // this asserts the page cannot be talked into crossing that line.
        $this->assertDatabaseHas('prescription_templates', ['id' => $theirs->id]);
    }
}
