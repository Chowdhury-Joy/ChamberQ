<?php

namespace Tests\Unit;

use App\Models\ScheduleSession;
use App\Support\ScheduleSessionPace;
use Tests\TestCase;

class ScheduleSessionPaceTest extends TestCase
{
    public function test_minutes_per_patient_for_typical_evening(): void
    {
        $session = new ScheduleSession([
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 30,
        ]);

        $this->assertSame(6, ScheduleSessionPace::minutesPerPatient($session));
        $this->assertFalse(ScheduleSessionPace::isTight($session));
    }

    public function test_minutes_per_patient_flags_tight_schedule(): void
    {
        $session = new ScheduleSession([
            'start_time' => '17:00',
            'end_time' => '20:00',
            'slot_cap' => 60,
        ]);

        $this->assertSame(3, ScheduleSessionPace::minutesPerPatient($session));
        $this->assertTrue(ScheduleSessionPace::isTight($session));
    }

    public function test_staff_cap_includes_overflow_stools(): void
    {
        $session = new ScheduleSession([
            'slot_cap' => 30,
            'walk_in_overflow_cap' => 5,
        ]);

        $this->assertSame(30, ScheduleSessionPace::publishedCap($session));
        $this->assertSame(35, ScheduleSessionPace::staffCap($session));
    }
}
