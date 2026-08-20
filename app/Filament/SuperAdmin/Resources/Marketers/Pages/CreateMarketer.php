<?php

namespace App\Filament\SuperAdmin\Resources\Marketers\Pages;

use App\Filament\SuperAdmin\Resources\Marketers\MarketerResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateMarketer extends CreateRecord
{
    protected static string $resource = MarketerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = User::provision([
            'name' => $this->data['user_name'] ?? $data['display_name'],
            'email' => $this->data['user_email'],
            'password' => Hash::make($this->data['user_password']),
            'role' => User::ROLE_MARKETER,
            'tenant_id' => null,
        ]);

        $data['user_id'] = $user->id;

        return $data;
    }
}
