<?php

namespace App\Filament\TenantAdmin\Resources\AttendanceRecords\Pages;

use App\Filament\TenantAdmin\Resources\AttendanceRecords\AttendanceRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceRecord extends EditRecord
{
    protected static string $resource = AttendanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
