<?php

namespace App\Filament\TenantAdmin\Resources\LeaveRequests\Pages;

use App\Filament\TenantAdmin\Resources\LeaveRequests\LeaveRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;
}
