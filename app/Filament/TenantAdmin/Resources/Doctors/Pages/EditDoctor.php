<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Pages;

use App\Filament\TenantAdmin\Resources\Doctors\DoctorResource;
use App\Models\Doctor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDoctor extends EditRecord
{
    protected static string $resource = DoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['notify_channels'] = ($this->getRecord() instanceof Doctor)
            ? $this->getRecord()->notifyChannels()
            : Doctor::defaultNotifyChannels();

        return $data;
    }
}
