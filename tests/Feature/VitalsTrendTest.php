<?php

namespace Tests\Feature;

use App\Support\VitalsTrend;
use Tests\TestCase;

class VitalsTrendTest extends TestCase
{
    public function test_line_chart_needs_at_least_two_points(): void
    {
        $this->assertNull(VitalsTrend::lineChart([
            ['label' => '1 Jan', 'value' => 70],
        ]));

        $chart = VitalsTrend::lineChart([
            ['label' => '1 Jan', 'value' => 70],
            ['label' => '8 Jan', 'value' => 71],
        ]);

        $this->assertNotNull($chart);
        $this->assertStringStartsWith('M ', $chart['path']);
    }
}
