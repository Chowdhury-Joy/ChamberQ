<?php

namespace App\Filament\SuperAdmin\Resources\Marketers\Pages;

use App\Filament\SuperAdmin\Resources\Marketers\MarketerResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Js;

class EditMarketer extends EditRecord
{
    protected static string $resource = MarketerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyReferralLink')
                ->label(__('Copy referral link'))
                ->icon('heroicon-o-clipboard')
                ->alpineClickHandler(fn (): string => 'window.navigator.clipboard.writeText('.Js::from($this->record->referralUrl()).')')
                ->action(function (): void {
                    Notification::make()
                        ->title(__('Referral link copied'))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
