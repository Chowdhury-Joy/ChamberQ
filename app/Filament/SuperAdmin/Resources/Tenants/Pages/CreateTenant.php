<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Pages;

use App\Filament\SuperAdmin\Resources\Tenants\TenantResource;
use App\Models\DiscountCode;
use App\Models\Tenant;
use App\Services\CommissionService;
use App\Services\TenantUserBootstrapService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /** @var array<string, mixed> */
    private array $loginBootstrap = [];

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

        $this->loginBootstrap = [
            'owner_email' => $data['initial_owner_email'] ?? null,
            'owner_name' => $data['initial_owner_name'] ?? null,
            'doctor_email' => $data['initial_doctor_email'] ?? null,
            'doctor_name' => $data['initial_doctor_name'] ?? null,
            'helper_email' => $data['initial_helper_email'] ?? null,
            'helper_name' => $data['initial_helper_name'] ?? null,
        ];

        // Not tenant columns — handled in afterCreate.
        unset(
            $data['initial_doctor_email'],
            $data['initial_doctor_name'],
            $data['initial_owner_email'],
            $data['initial_owner_name'],
            $data['initial_helper_email'],
            $data['initial_helper_name'],
        );

        return $data;
    }

    protected function beforeCreate(): void
    {
        $state = $this->form->getState();
        $emails = array_filter(array_map(
            fn ($email): string => strtolower(trim((string) $email)),
            [
                $state['initial_owner_email'] ?? '',
                $state['initial_doctor_email'] ?? '',
                $state['initial_helper_email'] ?? '',
            ],
        ));

        if (count($emails) !== count(array_unique($emails))) {
            throw ValidationException::withMessages([
                'initial_doctor_email' => __('Owner, doctor, and helper emails must be different.'),
            ]);
        }
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

        if ($tenant->marketer_id || $tenant->medical_representative_id) {
            $commissions->createPendingSetupCommission($tenant);
        }

        $formState = $this->loginBootstrap;
        $bootstrap = app(TenantUserBootstrapService::class);

        $ownerPassword = Str::password(16);
        $helperPassword = Str::password(16);
        $doctorPassword = Str::password(16);

        $owner = $bootstrap->ensureOwnerLogin(
            $tenant,
            $formState['owner_email'] ?? null,
            $formState['owner_name'] ?? null,
            $ownerPassword,
        );
        $helper = $bootstrap->ensureHelperLogin(
            $tenant,
            filled($formState['helper_email'] ?? null) ? $formState['helper_email'] : null,
            $formState['helper_name'] ?? null,
            $helperPassword,
        );
        $doctor = $bootstrap->ensureDoctorLogin(
            $tenant,
            $formState['doctor_email'] ?? null,
            $formState['doctor_name'] ?? null,
            $doctorPassword,
        );

        $lines = [];
        if ($owner->wasRecentlyCreated) {
            $lines[] = __('Owner :email / :password', ['email' => $owner->email, 'password' => $ownerPassword]);
        }
        if ($helper->wasRecentlyCreated) {
            $lines[] = __('ChamberQ helper :email / :password', ['email' => $helper->email, 'password' => $helperPassword]);
        }
        if ($doctor->wasRecentlyCreated) {
            $lines[] = __('Doctor :email / :password', ['email' => $doctor->email, 'password' => $doctorPassword]);
        }

        if ($lines !== []) {
            Notification::make()
                ->title(__('Logins created — copy these passwords now'))
                ->body(implode("\n", $lines))
                ->success()
                ->persistent()
                ->send();
        }
    }
}
