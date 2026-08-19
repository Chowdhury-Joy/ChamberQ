<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\Doctors\DoctorResource;
use App\Filament\TenantAdmin\Resources\Doctors\Pages\EditDoctor;
use App\Models\Doctor;
use Filament\Resources\Pages\CreateRecord;

class CreateDoctor extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = DoctorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['notify_channels'] = array_replace_recursive(
            Doctor::defaultNotifyChannels(),
            is_array($data['notify_channels'] ?? null) ? $data['notify_channels'] : [],
        );

        return EditDoctor::applyPracticeRules($data);
    }
}
