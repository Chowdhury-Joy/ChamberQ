<?php

namespace Tests\Feature;

use App\Support\RxSafety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RxSafetyTest extends TestCase
{
    // The duplicate and allergy rules are pure functions, but the interaction
    // rule reads the `drug_interactions` table.
    use RefreshDatabase;

    public function test_duplicate_generic_warns(): void
    {
        $warnings = RxSafety::duplicateWarnings([
            ['medicine_name' => 'Seclo', 'generic_name' => 'Esomeprazole'],
            ['medicine_name' => 'Maxpro', 'generic_name' => 'Esomeprazole'],
        ]);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('esomeprazole', strtolower($warnings[0]));
    }

    public function test_duplicate_brand_warns(): void
    {
        $warnings = RxSafety::duplicateWarnings([
            ['medicine_name' => 'NAPA', 'generic_name' => 'Paracetamol'],
            ['medicine_name' => 'Napa', 'generic_name' => 'Paracetamol'],
        ]);

        $this->assertTrue(
            collect($warnings)->contains(fn (string $line) => str_contains(strtolower($line), 'duplicate brand'))
        );
    }

    public function test_allergy_match_warns_without_blocking(): void
    {
        $warnings = RxSafety::allergyWarnings('Paracetamol', [
            ['medicine_name' => 'NAPA', 'generic_name' => 'Paracetamol'],
        ]);

        $this->assertNotEmpty($warnings);
    }

    public function test_blank_allergy_is_silent(): void
    {
        $this->assertSame([], RxSafety::allergyWarnings(null, [
            ['medicine_name' => 'NAPA', 'generic_name' => 'Paracetamol'],
        ]));
    }

    public function test_interaction_pair_warns_across_salt_forms(): void
    {
        $this->seedInteraction('warfarin', 'diclofenac', 'avoid');

        $warnings = RxSafety::allWarnings(null, [
            ['medicine_name' => 'WARF', 'generic_name' => 'Warfarin Sodium'],
            ['medicine_name' => 'VOLTALIN', 'generic_name' => 'Diclofenac Sodium'],
        ]);

        // The catalogue stores salts; the pair list stores bare ingredients.
        // If the salt-stripping in DrugIngredients regresses, this stops firing
        // silently — which is the failure this whole feature exists to avoid.
        $this->assertTrue(
            collect($warnings)->contains(fn (string $w): bool => str_contains($w, 'Avoid together')),
            'Expected warfarin + diclofenac to warn despite both being stored as salts.',
        );
    }

    public function test_pair_matches_regardless_of_the_order_the_doctor_typed_them(): void
    {
        $this->seedInteraction('warfarin', 'diclofenac', 'avoid');

        $forwards = RxSafety::interactionWarnings([
            ['medicine_name' => 'WARF', 'generic_name' => 'Warfarin'],
            ['medicine_name' => 'VOLTALIN', 'generic_name' => 'Diclofenac'],
        ]);
        $backwards = RxSafety::interactionWarnings([
            ['medicine_name' => 'VOLTALIN', 'generic_name' => 'Diclofenac'],
            ['medicine_name' => 'WARF', 'generic_name' => 'Warfarin'],
        ]);

        $this->assertCount(1, $forwards);
        $this->assertCount(1, $backwards);
    }

    public function test_a_medicine_with_no_generic_name_is_reported_as_unchecked(): void
    {
        $items = [
            ['medicine_name' => 'SOMEBRAND', 'generic_name' => null],
            ['medicine_name' => 'NAPA', 'generic_name' => 'Paracetamol'],
        ];

        $this->assertSame(['SOMEBRAND'], RxSafety::uncheckedMedicines($items));

        // Saying nothing would let the doctor read "no warning" as "checked and
        // clear" for a drug that was never examined at all.
        $this->assertTrue(
            collect(RxSafety::allWarnings(null, $items))
                ->contains(fn (string $w): bool => str_contains($w, 'Not checked for clashes')),
        );
    }

    public function test_a_combination_product_does_not_clash_with_itself(): void
    {
        $this->seedInteraction('amoxicillin', 'clavulanic acid', 'serious');

        $warnings = RxSafety::interactionWarnings([
            ['medicine_name' => 'AUGMENTIN', 'generic_name' => 'Amoxicillin + Clavulanic Acid'],
        ]);

        // Both ingredients come off one prescription line. That is one product,
        // not two drugs prescribed together.
        $this->assertSame([], $warnings);
    }

    public function test_every_surface_that_shows_a_warning_also_shows_the_disclaimer(): void
    {
        $this->seedInteraction('warfarin', 'diclofenac', 'avoid');

        $items = [
            ['medicine_name' => 'WARF', 'generic_name' => 'Warfarin'],
            ['medicine_name' => 'VOLTALIN', 'generic_name' => 'Diclofenac'],
        ];

        $rendered = view('filament.tenant-admin.components.rx-safety-warnings', [
            'allergies' => null,
            'items' => $items,
        ])->render();

        // No clinician is named against this list by owner decision, so the
        // disclaimer is the only thing telling a doctor how much weight these
        // warnings carry. A display that shows warnings without it is making a
        // claim the list cannot support.
        $this->assertStringContainsString('Avoid together', $rendered);
        $this->assertStringContainsString(__(RxSafety::DISCLAIMER), $rendered);

        // The desktop pad renders its warnings in Alpine, so assert the
        // disclaimer is in that template too — it is a separate surface and
        // would otherwise be free to drop it.
        $desk = file_get_contents(resource_path('views/filament/tenant-admin/components/rx-desk.blade.php'));
        $this->assertStringContainsString('RxSafety::DISCLAIMER', $desk);
    }

    private function seedInteraction(string $a, string $b, string $severity): void
    {
        $pair = [$a, $b];
        sort($pair);

        \App\Models\DrugInteraction::create([
            'ingredient_a' => $pair[0],
            'ingredient_b' => $pair[1],
            'severity' => $severity,
            'effect' => 'Test effect',
            'action' => 'Test action',
        ]);
    }
}
