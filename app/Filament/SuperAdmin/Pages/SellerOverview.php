<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Services\SellerOverviewService;
use Filament\Pages\Page;

class SellerOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Client Health';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Client Health';

    protected string $view = 'filament.super-admin.pages.seller-overview';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->role === \App\Models\User::ROLE_SUPER_ADMIN && $user->tenant_id === null;
    }

    public function getOverviewService(): SellerOverviewService
    {
        return app(SellerOverviewService::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getQuietClients(): array
    {
        return $this->getOverviewService()->quietClients()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getGoLiveFunnel(): array
    {
        return $this->getOverviewService()->goLiveFunnel()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSmsWarnings(): array
    {
        return $this->getOverviewService()->smsCreditWarnings()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getOverduePayments(): array
    {
        return $this->getOverviewService()->overduePayments()->all();
    }

    /**
     * @return array<string, string>
     */
    public function getFunnelStepLabels(): array
    {
        return [
            'account_made' => 'Account made',
            'chambers_added' => 'Chambers added',
            'schedule_set' => 'Schedule set',
            'website_live' => 'Website live',
            'first_booking' => 'First booking',
            'first_live_session' => 'First live session',
        ];
    }
}
