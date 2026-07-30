<?php

namespace App\Filament\SuperAdmin\Resources\Marketers\Pages;

use App\Filament\SuperAdmin\Resources\Marketers\MarketerResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMarketer extends EditRecord
{
    protected static string $resource = MarketerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyReferralLink')
                ->label(__('Copy referral link'))
                ->icon('heroicon-o-link')
                ->action(function (): void {
                    Notification::make()
                        ->title(__('Referral link'))
                        ->body($this->record->referralUrl())
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
