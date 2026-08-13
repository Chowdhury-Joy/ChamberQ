<?php

namespace Tests\Unit;

use App\Support\DoseSchedule;
use App\Support\PrescriptionQuantity;
use Tests\TestCase;

/**
 * Frequency × duration, and `1+0+1` written out for the patient.
 *
 * The rule both classes share: read both columns unambiguously or say nothing.
 * A number or a sentence invented here is read as the doctor's instruction, so
 * every "returns null" case below is as much the feature as the arithmetic is.
 */
class PrescriptionQuantityTest extends TestCase
{
    public function test_it_multiplies_the_doctors_own_line_out(): void
    {
        $this->assertSame(14, PrescriptionQuantity::total('1+0+1', '7 days'));
        $this->assertSame(15, PrescriptionQuantity::total('1+1+1', '5 days'));
        $this->assertSame(30, PrescriptionQuantity::total('0+0+1', '1 month'));
        $this->assertSame(28, PrescriptionQuantity::total('1+0+1', '2 weeks'));
        $this->assertSame(10, PrescriptionQuantity::total('½+0+½', '10 days'));
    }

    public function test_it_says_nothing_when_either_column_cannot_be_read(): void
    {
        // No daily count.
        $this->assertNull(PrescriptionQuantity::total('SOS', '5 days'));
        $this->assertNull(PrescriptionQuantity::total('as directed', '5 days'));
        // No end.
        $this->assertNull(PrescriptionQuantity::total('1+0+1', 'Continue'));
        $this->assertNull(PrescriptionQuantity::total('1+0+1', 'until better'));
        // Nothing at all.
        $this->assertNull(PrescriptionQuantity::total(null, null));
        $this->assertNull(PrescriptionQuantity::total('', ''));
        // A single slot is not the plus notation; it could be anything.
        $this->assertNull(PrescriptionQuantity::total('2', '5 days'));
        // One unreadable position poisons the whole line.
        $this->assertNull(PrescriptionQuantity::total('1+x+1', '5 days'));
    }

    public function test_a_half_dose_rounds_up_rather_than_leaving_the_patient_short(): void
    {
        // 0.5/day for 7 days is 3.5 — you cannot dispense half a strip short.
        $this->assertSame(4, PrescriptionQuantity::total('½+0+0', '7 days'));
    }

    public function test_it_writes_the_three_slot_pattern_out_in_bangla(): void
    {
        $this->assertSame('সকাল ১টি · রাত ১টি', DoseSchedule::sentence('1+0+1'));
        $this->assertSame('সকাল ১টি · দুপুর ১টি · রাত ১টি', DoseSchedule::sentence('1+1+1'));
        $this->assertSame('রাত ১টি', DoseSchedule::sentence('0+0+1'));
        $this->assertSame('সকাল ½টি · রাত ½টি', DoseSchedule::sentence('½+0+½'));
    }

    public function test_it_refuses_to_write_out_anything_it_would_have_to_guess(): void
    {
        // Only the three-slot form has fixed, universally understood positions
        // here. A wrong sentence about when to take a medicine is worse than
        // leaving the doctor's own shorthand on the page.
        $this->assertNull(DoseSchedule::sentence('SOS'));
        $this->assertNull(DoseSchedule::sentence('TDS'));
        $this->assertNull(DoseSchedule::sentence('1+1'));
        $this->assertNull(DoseSchedule::sentence('1+0+1+1'));
        $this->assertNull(DoseSchedule::sentence('1+x+1'));
        $this->assertNull(DoseSchedule::sentence('0+0+0'));
        $this->assertNull(DoseSchedule::sentence(null));
    }
}
