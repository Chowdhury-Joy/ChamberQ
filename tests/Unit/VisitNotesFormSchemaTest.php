<?php

namespace Tests\Unit;

use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use PHPUnit\Framework\TestCase;

class VisitNotesFormSchemaTest extends TestCase
{
    public function test_normalize_submission_resolves_dose_other(): void
    {
        $normalized = VisitNotesFormSchema::normalizeSubmission([
            'prescription_items' => [
                ['medicine_name' => 'ACE', 'dose' => 'other', 'dose_other' => '625 mg'],
            ],
        ]);

        $this->assertSame('625 mg', $normalized['prescription_items'][0]['dose']);
    }

    public function test_normalize_submission_keeps_dose_preset(): void
    {
        $normalized = VisitNotesFormSchema::normalizeSubmission([
            'prescription_items' => [
                ['medicine_name' => 'ACE', 'dose' => '500 mg'],
            ],
        ]);

        $this->assertSame('500 mg', $normalized['prescription_items'][0]['dose']);
    }

    public function test_prefill_dose_from_strength_uses_raw_catalogue_value(): void
    {
        $prefill = VisitNotesFormSchema::prefillDoseFromStrength('40 mg');

        $this->assertSame('40 mg', $prefill['dose']);
        $this->assertNull($prefill['dose_other']);
    }
}
