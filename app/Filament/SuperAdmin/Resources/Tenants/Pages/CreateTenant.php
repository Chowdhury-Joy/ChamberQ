<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Pages;

use App\Filament\SuperAdmin\Resources\Tenants\TenantResource;
use App\Models\DiscountCode;
use App\Models\Tenant;
use App\Services\CommissionService;
use App\Services\TenantUserBootstrapService;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    public function mount(): void
    {
        parent::mount();

        $prefill = [
            'product_modules' => Tenant::productModules(),
        ];

        if ($marketerId = session('referral.marketer_id')) {
            $prefill['marketer_id'] = $marketerId;
            $prefill['referred_at'] = now();
        }

        if ($discountId = session('referral.discount_code_id')) {
            $prefill['discount_code_id'] = $discountId;
        }

        $this->form->fill($prefill);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['marketer_id']) && empty($data['referred_at'])) {
            $data['referred_at'] = now();
        }

        $modules = $data['product_modules'] ?? Tenant::productModules();
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

        // Not tenant columns — handled in afterCreate.
        unset($data['initial_doctor_email'], $data['initial_doctor_name']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $tenant = $this->record;
        $code = $tenant->discount_code_id
            ? DiscountCode::find($tenant->discount_code_id)
            : null;

        $commissions = app(CommissionService::class);
        $commissions->applyPricingToTenant($tenant, $code, countRedemption: true);
        $tenant->save();

        if ($tenant->marketer_id) {
            $commissions->createPendingSetupCommission($tenant);
        }

        $formState = $this->form->getState();
        app(TenantUserBootstrapService::class)->ensureDoctorLogin(
            $tenant,
            $formState['initial_doctor_email'] ?? null,
            $formState['initial_doctor_name'] ?? null,
        );
    }
}
