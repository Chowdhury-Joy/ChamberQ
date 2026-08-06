<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Services\ResearchDataService;
use Filament\Pages\Page;

class ResearchData extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Research data';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Research data';

    protected static ?string $slug = 'research';

    protected string $view = 'filament.super-admin.pages.research-data';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $planTier = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subMonths(3)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->role === \App\Models\User::ROLE_SUPER_ADMIN && $user->tenant_id === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResearchResults(): array
    {
        return app(ResearchDataService::class)->conditionCounts([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'plan_tier' => filled($this->planTier) ? $this->planTier : null,
        ]);
    }

    public function getMinGroupSize(): int
    {
        return ResearchDataService::MIN_GROUP_SIZE;
    }
}
