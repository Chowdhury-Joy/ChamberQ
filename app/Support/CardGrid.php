<?php

namespace App\Support;

final class CardGrid
{
    /**
     * Desktop column count for a card collection.
     * 2 or 4 cards → 2 columns; all other counts → 3 columns.
     */
    public static function desktopColumns(int $count): int
    {
        return in_array($count, [2, 4], true) ? 2 : 3;
    }

    /**
     * Filament StatsOverview / schema grid breakpoints.
     *
     * @return array<string, int>
     */
    public static function filamentColumns(int $count): array
    {
        if ($count < 1) {
            return ['default' => 1];
        }

        return [
            'default' => 1,
            'sm' => 2,
            'xl' => self::desktopColumns($count),
        ];
    }
}
