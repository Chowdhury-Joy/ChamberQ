<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Pages;

use App\Filament\SuperAdmin\Resources\Tenants\TenantResource;
use App\Models\DiscountCode;
use App\Services\CommissionService;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    public function mount(): void
    {
        parent::mount();

        $prefill = [];

        if ($marketerId = session('referral.marketer_id')) {
            $prefill['marketer_id'] = $marketerId;
            $prefill['referred_at'] = now();
        }

        if ($discountId = session('referral.discount_code_id')) {
            $prefill['discount_code_id'] = $discountId;
        }

        if ($prefill !== []) {
            $this->form->fill($prefill);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['marketer_id']) && empty($data['referred_at'])) {
            $data['referred_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $tenant = $this->record;
        $code = $tenant->discount_code_id
            ? DiscountCode::find($tenant->discount_code_id)
            : null;

        $commissions = app(CommissionService::class);
        $commissions->applyPricingToTenant($tenant, $code);
        $tenant->save();

        if ($tenant->marketer_id) {
            $commissions->createPendingSetupCommission($tenant);
        }
    }
}
