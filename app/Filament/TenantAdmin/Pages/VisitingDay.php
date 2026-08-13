<?php

namespace App\Filament\TenantAdmin\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class VisitingDay extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Visiting / camp';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'visiting-day';

    protected static ?string $title = 'Visiting / camp';

    protected string $view = 'filament.tenant-admin.pages.visiting-day';

    protected Width | string | null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canRecordVisitNotes() ?? false;
    }
}
