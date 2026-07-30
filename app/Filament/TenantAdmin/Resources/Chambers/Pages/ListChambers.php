<?php

namespace App\Filament\TenantAdmin\Resources\Chambers\Pages;

use App\Filament\TenantAdmin\Resources\Chambers\ChamberResource;
use App\Models\Chamber;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListChambers extends ListRecords
{
    protected static string $resource = ChamberResource::class;

    public function getSubheading(): ?string
    {
        $tenant = tenant();

        if (! $tenant || $tenant->isClinic()) {
            return null;
        }

        if (! $tenant->hasFeature('multiple_chambers')) {
            return __('Solo plan: one location only.');
        }

        $count = Chamber::count();
        $max = Tenant::SOLO_MAX_CHAMBERS;

        return __('Solo plan: up to :max locations (:count used).', [
            'max' => $max,
            'count' => $count,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => Gate::allows('create', Chamber::class)),
        ];
    }
}
