<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Pages;

use App\Filament\SuperAdmin\Resources\Tenants\TenantResource;
use App\Filament\SuperAdmin\Support\TenantBackupActions;
use App\Models\DiscountCode;
use App\Models\Tenant;
use App\Services\CommissionService;
use App\Services\SmsService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tenant = $this->record;
        $data['product_modules'] = $tenant instanceof Tenant
            ? $tenant->enabledProductModules()
            : Tenant::productModules();
        $data['module_stations'] = $tenant instanceof Tenant
            ? $tenant->hasStations()
            : false;
        $data['module_referrals'] = $tenant instanceof Tenant
            ? $tenant->hasReferrals()
            : false;
        $data['module_hr'] = $tenant instanceof Tenant
            ? $tenant->hasHr()
            : false;

        // Module keys are edited via product_modules — keep KeyValue for add-ons only.
        $flags = is_array($data['feature_flags'] ?? null) ? $data['feature_flags'] : [];
        foreach (Tenant::productModules() as $module) {
            unset($flags[$module]);
        }
        unset($flags[Tenant::MODULE_STATIONS], $flags[Tenant::MODULE_REFERRALS], $flags[Tenant::MODULE_HR]);
        $data['feature_flags'] = $flags;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['marketer_id']) && empty($data['referred_at']) && ! $this->record->referred_at) {
            $data['referred_at'] = now();
        }

        $modules = $data['product_modules'] ?? $this->record->enabledProductModules();
        $stations = (bool) ($data['module_stations'] ?? false);
        $referrals = (bool) ($data['module_referrals'] ?? false);
        $hr = (bool) ($data['module_hr'] ?? false);
        unset($data['product_modules'], $data['module_stations'], $data['module_referrals'], $data['module_hr']);

        $data['feature_flags'] = Tenant::featureFlagsWithModules(
            is_array($data['feature_flags'] ?? null) ? $data['feature_flags'] : [],
            is_array($modules) ? $modules : Tenant::productModules(),
        );
        $data['feature_flags'] = Tenant::mergeStationsFlag($data['feature_flags'], $stations);
        $data['feature_flags'] = Tenant::mergeOptInModuleFlag($data['feature_flags'], Tenant::MODULE_REFERRALS, $referrals);
        $data['feature_flags'] = Tenant::mergeOptInModuleFlag($data['feature_flags'], Tenant::MODULE_HR, $hr);

        return $data;
    }

    protected function afterSave(): void
    {
        $tenant = $this->record;

        // Read every `wasChanged()` answer up front. Re-pricing saves the tenant
        // again, and that second save re-syncs `$model->changes` — so asking
        // afterwards whether the marketer changed returns false, and the partner
        // silently loses their setup commission.
        $pricingChanged = $tenant->wasChanged([
            'plan_tier',
            'discount_code_id',
            'feature_flags',
            'offer_prescription_lifetime_free',
            'offer_prepaid_year_setup',
        ]);
        $discountChanged = $tenant->wasChanged('discount_code_id');
        $marketerChanged = $tenant->wasChanged('marketer_id');

        if ($pricingChanged) {
            $code = $tenant->discount_code_id
                ? DiscountCode::find($tenant->discount_code_id)
                : null;

            $commissions = app(CommissionService::class);
            $commissions->applyPricingToTenant(
                $tenant,
                $code,
                countRedemption: $discountChanged,
            );
            $tenant->save();
        }

        if ($marketerChanged && $tenant->marketer_id) {
            app(CommissionService::class)->createPendingSetupCommission($tenant);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmSetupPaid')
                ->label(__('Confirm setup paid'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => ! $this->record->hasSetupPaid())
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
                ->visible(fn () => $this->record->hasSetupPaid())
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
            Action::make('confirmYearPrepaid')
                ->label(__('Confirm 12 months prepaid'))
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->visible(fn () => $this->record->hasSetupPaid())
                ->schema([
                    TextInput::make('start_period')
                        ->label(__('First month'))
                        ->placeholder(now()->format('Y-m'))
                        ->default(now()->format('Y-m'))
                        ->required()
                        ->regex('/^\d{4}-\d{2}$/')
                        ->helperText(__('Twelve months from this period. Already-confirmed months are skipped.')),
                    TextInput::make('amount_paid')
                        ->label(__('Total amount paid'))
                        ->numeric()
                        ->default(fn () => (int) $this->record->monthly_amount_due * Tenant::PREPAID_YEAR_MONTHS)
                        ->required()
                        ->helperText(__('Default is 12 × monthly due. Already-confirmed months are skipped; a custom total is split across months still open.')),
                    Textarea::make('notes')
                        ->rows(2),
                ])
                ->action(function (array $data, CommissionService $commissions): void {
                    try {
                        $count = $commissions->confirmYearPrepaid(
                            $this->record,
                            auth()->user(),
                            $data['notes'] ?? null,
                            isset($data['amount_paid']) ? (int) $data['amount_paid'] : null,
                            $data['start_period'] ?? null,
                        );
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }
                    $this->record->refresh();

                    if ($count === 0) {
                        Notification::make()
                            ->title(__('No months left to confirm'))
                            ->body(__('The next 12 months already have a confirmed payment.'))
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('Year prepaid confirmed'))
                        ->body(__(':count months marked paid. Partner commission is owed on those months.', [
                            'count' => $count,
                        ]))
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
            TenantBackupActions::downloadAction()
                ->color('gray'),
            ActionGroup::make([
                TenantBackupActions::restoreAction(),
                DeleteAction::make(),
            ])
                ->label(__('Dangerous'))
                ->icon('heroicon-m-exclamation-triangle')
                ->color('gray')
                ->button()
                ->dropdownPlacement('bottom-end'),
        ];
    }
}
