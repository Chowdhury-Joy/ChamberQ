<?php

namespace Tests\Unit;

use App\Support\PrescriptionTiming;
use PHPUnit\Framework\TestCase;

class PrescriptionTimingTest extends TestCase
{
    public function test_latin_shorthand_ac_is_before_food(): void
    {
        $this->assertSame(PrescriptionTiming::BEFORE_FOOD, PrescriptionTiming::normalize('ac'));
    }

    public function test_latin_shorthand_pc_is_after_food(): void
    {
        $this->assertSame(PrescriptionTiming::AFTER_FOOD, PrescriptionTiming::normalize('pc'));
    }

    public function test_english_shorthand_unchanged(): void
    {
        $this->assertSame(PrescriptionTiming::AFTER_FOOD, PrescriptionTiming::normalize('af'));
        $this->assertSame(PrescriptionTiming::BEFORE_FOOD, PrescriptionTiming::normalize('bf'));
    }
}
