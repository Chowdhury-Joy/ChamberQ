<?php

namespace App\Filament\TenantAdmin\Resources\AttendanceRecords\Pages;

use App\Filament\TenantAdmin\Resources\AttendanceRecords\AttendanceRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceRecord extends CreateRecord
{
    protected static string $resource = AttendanceRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();

        return $data;
    }
}
