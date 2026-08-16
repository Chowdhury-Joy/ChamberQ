<?php

namespace App\Filament\TenantAdmin\Resources\AttendanceRecords\Pages;

use App\Filament\TenantAdmin\Resources\AttendanceRecords\AttendanceRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceRecords extends ListRecords
{
    protected static string $resource = AttendanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
