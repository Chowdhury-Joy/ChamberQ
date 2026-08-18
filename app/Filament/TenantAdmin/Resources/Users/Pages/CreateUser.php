<?php

namespace App\Filament\TenantAdmin\Resources\Users\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['role'] ?? null) === User::ROLE_HELPER) {
            throw ValidationException::withMessages([
                'role' => __('ChamberQ helper access cannot be created from this login.'),
            ]);
        }

        return $data;
    }
}
