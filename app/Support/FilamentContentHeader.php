<?php

namespace App\Support;

use Filament\Pages\BasePage;
use Filament\Pages\SimplePage;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Livewire;

final class FilamentContentHeader
{
    /**
     * @return array{title: ?string, backUrl: ?string, showBack: bool}
     */
    public static function resolve(?object $livewire = null): array
    {
        $livewire ??= Livewire::current();

        if (! $livewire instanceof BasePage || $livewire instanceof SimplePage) {
            return [
                'title' => null,
                'backUrl' => null,
                'showBack' => false,
            ];
        }

        $heading = $livewire->getHeading() ?? $livewire->getTitle();
        $title = $heading instanceof Htmlable
            ? trim(strip_tags($heading->toHtml()))
            : trim(strip_tags((string) $heading));

        $showBack = $livewire instanceof EditRecord
            || $livewire instanceof CreateRecord;

        $backUrl = null;

        if ($showBack && method_exists($livewire, 'getResourceUrl')) {
            $backUrl = $livewire->getResourceUrl();
        }

        return [
            'title' => $title !== '' ? $title : null,
            'backUrl' => $backUrl,
            'showBack' => $showBack && filled($backUrl),
        ];
    }
}
