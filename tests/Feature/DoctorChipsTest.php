<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Pages\MyMedicines;
use App\Models\Domain;
use App\Models\DoctorChip;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DoctorChipService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Advice and History chips on the Rx desk are the doctor's own list,
 * curated on My medicines exactly like the medicines above them.
 */
class DoctorChipsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $doctor;

    private User $otherDoctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'doctor-chips', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'doctor-chips.localhost', 'tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);

        $this->doctor = User::create([
            'name' => 'Dr Chip',
            'email' => 'chip@doc.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->otherDoctor = User::create([
            'name' => 'Dr Other',
            'email' => 'other@doc.test',
            'password' => Hash::make('secret'),
            'role' => User::ROLE_DOCTOR,
            'tenant_id' => $this->tenant->id,
        ]);

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function onPage(): \Livewire\Features\SupportTesting\Testable
    {
        tenancy()->initialize($this->tenant);
        Filament::setCurrentPanel(Filament::getPanel('tenantAdmin'));
        $this->actingAs($this->doctor);

        return Livewire::test(MyMedicines::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function chips(string $kind, ?User $user = null): array
    {
        return app(DoctorChipService::class)->forDoctor($user ?? $this->doctor, $kind);
    }

    public function test_a_doctor_starts_with_the_shipped_chips(): void
    {
        tenancy()->initialize($this->tenant);

        $labels = collect($this->chips(DoctorChip::KIND_ADVICE))->pluck('label')->all();

        $this->assertContains('Rest', $labels);
        $this->assertContains(
            'বিশ্রাম নিন',
            collect($this->chips(DoctorChip::KIND_ADVICE))->pluck('text')->all(),
        );
        $this->assertContains('HTN', collect($this->chips(DoctorChip::KIND_HISTORY))->pluck('label')->all());
    }

    public function test_doctor_can_add_an_advice_chip_with_a_bangla_line(): void
    {
        $this->onPage()
            ->callAction('createAdviceChip', [
                'label' => 'Walk 30 minutes',
                'text' => 'প্রতিদিন ৩০ মিনিট হাঁটুন',
            ])
            ->assertSee('Walk 30 minutes');

        $chip = collect($this->chips(DoctorChip::KIND_ADVICE))->firstWhere('label', 'Walk 30 minutes');

        $this->assertNotNull($chip);
        $this->assertSame('প্রতিদিন ৩০ মিনিট হাঁটুন', $chip['text']);
        $this->assertFalse($chip['is_default']);
    }

    public function test_an_advice_chip_without_a_bangla_line_prints_its_label(): void
    {
        $this->onPage()->callAction('createAdviceChip', ['label' => 'Stop smoking']);

        $chip = collect($this->chips(DoctorChip::KIND_ADVICE))->firstWhere('label', 'Stop smoking');

        $this->assertSame('Stop smoking', $chip['text']);
    }

    public function test_doctor_can_edit_a_shipped_chip_without_touching_another_doctors(): void
    {
        $this->onPage()->callAction(
            'editAdviceChip',
            ['label' => 'Full rest', 'text' => 'পূর্ণ বিশ্রাম নিন'],
            ['chipId' => 'default:rest'],
        );

        $mine = collect($this->chips(DoctorChip::KIND_ADVICE))->firstWhere('key', 'rest');
        $theirs = collect($this->chips(DoctorChip::KIND_ADVICE, $this->otherDoctor))->firstWhere('key', 'rest');

        $this->assertSame('Full rest', $mine['label']);
        $this->assertSame('পূর্ণ বিশ্রাম নিন', $mine['text']);
        $this->assertTrue($mine['is_default'], 'An edited built-in is still the built-in, not a second chip.');
        $this->assertSame('Rest', $theirs['label']);
    }

    public function test_removing_a_shipped_chip_hides_it_and_it_can_be_restored(): void
    {
        $page = $this->onPage()->callAction('removeAdviceChip', arguments: ['chipId' => 'default:rest']);

        $this->assertNull(collect($this->chips(DoctorChip::KIND_ADVICE))->firstWhere('key', 'rest'));

        $page->callAction('restoreAdviceChip', arguments: ['chipId' => 'default:rest']);

        $this->assertNotNull(collect($this->chips(DoctorChip::KIND_ADVICE))->firstWhere('key', 'rest'));
    }

    public function test_removing_a_chip_the_doctor_added_deletes_it(): void
    {
        $page = $this->onPage()->callAction('createAdviceChip', ['label' => 'Gargle warm water']);

        $chip = collect($this->chips(DoctorChip::KIND_ADVICE))->firstWhere('label', 'Gargle warm water');

        $page->callAction('removeAdviceChip', arguments: ['chipId' => $chip['id']]);

        $this->assertSame(0, DoctorChip::query()->where('label', 'Gargle warm water')->count());
    }

    public function test_a_history_chip_can_be_kept_behind_more(): void
    {
        $this->onPage()->callAction('createHistoryChip', [
            'label' => 'Hep B',
            'is_primary' => false,
        ]);

        $chip = collect($this->chips(DoctorChip::KIND_HISTORY))->firstWhere('label', 'Hep B');

        $this->assertNotNull($chip);
        $this->assertFalse($chip['is_primary']);
    }

    public function test_the_star_on_the_desk_saves_an_advice_line_once(): void
    {
        tenancy()->initialize($this->tenant);

        $chips = app(DoctorChipService::class);

        $first = $chips->saveAdviceLine($this->doctor, '  রোদে বের হবেন না  ');
        $again = $chips->saveAdviceLine($this->doctor, 'রোদে বের হবেন না');

        $this->assertNotNull($first);
        $this->assertSame($first->id, $again->id, 'Starring the same line twice must not make a second chip.');
        $this->assertSame('রোদে বের হবেন না', $first->insertedText());
        $this->assertSame(
            1,
            DoctorChip::query()->where('user_id', $this->doctor->id)->count(),
        );
    }

    public function test_a_blank_line_is_never_saved_as_a_chip(): void
    {
        tenancy()->initialize($this->tenant);

        $this->assertNull(app(DoctorChipService::class)->saveAdviceLine($this->doctor, "   \n "));
        $this->assertSame(0, DoctorChip::query()->count());
    }

    public function test_chips_do_not_leak_between_tenants(): void
    {
        $this->onPage()->callAction('createAdviceChip', ['label' => 'Chamber only line']);

        $other = Tenant::create(['id' => 'doctor-chips-2', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'doctor-chips-2.localhost', 'tenant_id' => $other->id]);

        tenancy()->end();
        tenancy()->initialize($other);

        $this->assertSame(0, DoctorChip::query()->count());
    }
}
