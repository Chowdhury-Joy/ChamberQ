<?php

namespace App\Filament\Marketer\Widgets;

use Filament\Widgets\Widget;

class ReferralLinkWidget extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.marketer.widgets.referral-link';

    protected int|string|array $columnSpan = 'full';

    public function getReferralUrl(): ?string
    {
        return auth()->user()?->marketerProfile?->referralUrl();
    }

    public function getReferralCode(): ?string
    {
        return auth()->user()?->marketerProfile?->code;
    }
}
