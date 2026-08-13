<?php

namespace App\Filament\TenantAdmin\Support;

use App\Filament\TenantAdmin\Resources\WebPages\Pages\EditWebPage;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

/**
 * Admin-only page-builder habits: closed lids, short labels, clone/reorder.
 * Does not change what patients see on the live site.
 */
final class PageBuilderChrome
{
    /**
     * Closed-row label: block type plus the first headline-like field.
     *
     * @return Closure(array<string, mixed>|null): string
     */
    public static function blockLid(string $typeLabel): Closure
    {
        return function (?array $state) use ($typeLabel): string {
            if (empty($state)) {
                return $typeLabel;
            }

            $title = $state['headline']
                ?? $state['heading']
                ?? $state['eyebrow']
                ?? $state['quote']
                ?? null;

            if (! filled($title) || ! is_string($title)) {
                return $typeLabel;
            }

            $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');

            return $title === '' ? $typeLabel : $typeLabel.' — '.Str::limit($title, 60);
        };
    }

    /**
     * Nested lists start closed, can be cloned, and show a short name on the lid.
     *
     * @param  Closure(array<string, mixed>): ?string  $itemLabel
     */
    public static function lid(Repeater $repeater, Closure $itemLabel): Repeater
    {
        return $repeater
            ->collapsible()
            ->collapsed()
            ->cloneable()
            ->reorderable()
            ->collapseAllAction(fn (Action $action): Action => $action->hidden())
            ->expandAllAction(fn (Action $action): Action => $action->hidden())
            ->itemLabel($itemLabel);
    }

    public static function nestedName(array $state, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $state[$key] ?? null;

            if (is_string($value) && filled($value)) {
                return $value;
            }
        }

        return null;
    }

    public static function numberedName(array $state, string $numberKey, string $titleKey): ?string
    {
        $number = trim((string) ($state[$numberKey] ?? ''));
        $title = trim((string) ($state[$titleKey] ?? ''));

        if ($number !== '' && $title !== '') {
            return $number.' — '.$title;
        }

        return $title !== '' ? $title : ($number !== '' ? $number : null);
    }

    public static function isHomepageEditor(?LivewireComponent $livewire = null): bool
    {
        if (! $livewire instanceof EditWebPage) {
            return false;
        }

        return ($livewire->getRecord()?->slug ?? null) === '/';
    }
}
