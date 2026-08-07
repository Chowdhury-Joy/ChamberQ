<?php

namespace Tests\Unit;

use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use Carbon\Carbon;
use Tests\TestCase;

class FollowUpDisplayTest extends TestCase
{
    public function test_relative_follow_up_includes_human_phrase_and_date(): void
    {
        Carbon::setTestNow('2026-08-07');

        $label = VisitNotesFormSchema::followUpDisplayLabel(now()->addWeeks(2));

        $this->assertNotNull($label);
        $this->assertStringContainsString('2 weeks', $label);
        $this->assertStringContainsString('21 August 2026', $label);
    }

    public function test_as_needed_note_takes_priority_over_date(): void
    {
        $label = VisitNotesFormSchema::followUpDisplayLabel(now()->addWeek(), 'Come back if fever continues');

        $this->assertSame('Come back if fever continues', $label);
    }
}
