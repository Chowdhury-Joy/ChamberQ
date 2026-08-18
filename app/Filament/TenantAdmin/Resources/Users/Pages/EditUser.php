<?php

namespace App\Filament\TenantAdmin\Resources\Users\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof User && $record->isHelper()) {
            unset($data['role'], $data['email']);
        }

        if (($data['role'] ?? null) === User::ROLE_HELPER) {
            throw ValidationException::withMessages([
                'role' => __('ChamberQ helper access cannot be created from this login.'),
            ]);
        }

        return $data;
    }
}
