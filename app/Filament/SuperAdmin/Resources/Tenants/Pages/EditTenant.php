<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Pages;

use App\Filament\SuperAdmin\Resources\Tenants\TenantResource;
use App\Services\SmsService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('topUpSms')
                ->label(__('Top up SMS'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->schema([
                    TextInput::make('credits')
                        ->label(__('Credits to add'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->helperText(__('Prepaid packs: 200 / ৳100 · 500 / ৳225 · 2,000 / ৳800')),
                ])
                ->action(function (array $data, SmsService $sms): void {
                    $sms->topUp($this->record, (int) $data['credits']);
                    $this->record->refresh();

                    Notification::make()
                        ->title(__('SMS wallet topped up'))
                        ->body(__('Balance is now :balance credits.', [
                            'balance' => $this->record->sms_balance,
                        ]))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
