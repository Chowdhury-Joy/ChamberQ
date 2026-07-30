<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Pages;

use App\Filament\SuperAdmin\Resources\Tenants\TenantResource;
use App\Services\CommissionService;
use App\Services\SmsService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmSetupPaid')
                ->label(__('Confirm setup paid'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->marketer_id && ! $this->record->hasSetupPaid())
                ->schema([
                    TextInput::make('amount_paid')
                        ->label(__('Amount paid'))
                        ->numeric()
                        ->default(fn () => $this->record->setup_amount_due)
                        ->required(),
                    Textarea::make('notes')
                        ->rows(2),
                ])
                ->action(function (array $data, CommissionService $commissions): void {
                    $commissions->confirmSetupPayment(
                        $this->record,
                        auth()->user(),
                        $data['notes'] ?? null,
                        isset($data['amount_paid']) ? (int) $data['amount_paid'] : null,
                    );
                    $this->record->refresh();

                    Notification::make()
                        ->title(__('Setup payment confirmed'))
                        ->success()
                        ->send();
                }),
            Action::make('confirmMonthlyPaid')
                ->label(__('Confirm monthly paid'))
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->visible(fn () => $this->record->marketer_id && $this->record->hasSetupPaid())
                ->schema([
                    TextInput::make('period')
                        ->label(__('Billing period'))
                        ->placeholder(now()->format('Y-m'))
                        ->default(now()->format('Y-m'))
                        ->required()
                        ->regex('/^\d{4}-\d{2}$/'),
                    TextInput::make('amount_paid')
                        ->label(__('Amount paid'))
                        ->numeric()
                        ->default(fn () => $this->record->monthly_amount_due)
                        ->required(),
                    Textarea::make('notes')
                        ->rows(2),
                ])
                ->action(function (array $data, CommissionService $commissions): void {
                    $commissions->confirmMonthlyPayment(
                        $this->record,
                        $data['period'],
                        auth()->user(),
                        $data['notes'] ?? null,
                        isset($data['amount_paid']) ? (int) $data['amount_paid'] : null,
                    );

                    Notification::make()
                        ->title(__('Monthly payment confirmed'))
                        ->body(__('Period :period', ['period' => $data['period']]))
                        ->success()
                        ->send();
                }),
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
