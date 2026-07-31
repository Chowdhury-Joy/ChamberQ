<?php

namespace Tests\Unit;

use App\Support\CardGrid;
use PHPUnit\Framework\TestCase;

class CardGridTest extends TestCase
{
    public function test_desktop_columns_use_two_for_two_or_four_cards(): void
    {
        $this->assertSame(2, CardGrid::desktopColumns(2));
        $this->assertSame(2, CardGrid::desktopColumns(4));
    }

    public function test_desktop_columns_use_three_for_other_counts(): void
    {
        $this->assertSame(3, CardGrid::desktopColumns(1));
        $this->assertSame(3, CardGrid::desktopColumns(3));
        $this->assertSame(3, CardGrid::desktopColumns(5));
        $this->assertSame(3, CardGrid::desktopColumns(6));
    }
}
