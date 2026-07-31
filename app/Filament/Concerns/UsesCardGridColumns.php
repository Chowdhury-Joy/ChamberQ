<?php

namespace App\Filament\Concerns;

use App\Support\CardGrid;

trait UsesCardGridColumns
{
    /**
     * @return int | array<string, int | null> | null
     */
    protected function getColumns(): int | array | null
    {
        return CardGrid::filamentColumns(count($this->getCachedStats()));
    }
}
