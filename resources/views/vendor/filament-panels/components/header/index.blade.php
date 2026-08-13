@props([
    'actions' => [],
    'actionsAlignment' => null,
    'breadcrumbs' => [],
    'heading' => null,
    'subheading' => null,
])

@php
    use App\Support\FilamentContentHeader;
    use Filament\Support\Icons\Heroicon;

    $contentHeader = FilamentContentHeader::resolve();
    $displayTitle = $contentHeader['title'] ?? $heading;
    $showBack = $contentHeader['showBack'];
    $backUrl = $contentHeader['backUrl'];
@endphp

<header
    {{
        $attributes->class([
            'fi-header',
            'fi-content-shell-header',
            'fi-page-header-has-back' => $showBack && filled($backUrl),
        ])
    }}
>
    <div class="fi-page-header-title-row">
        @if ($showBack && filled($backUrl))
            <a
                href="{{ $backUrl }}"
                class="fi-page-header-back"
                aria-label="{{ __('filament-panels::resources/pages/edit-record.breadcrumb') }}"
            >
                <x-filament::icon
                    :icon="Heroicon::OutlinedArrowLeft"
                    class="fi-page-header-back-icon"
                />
            </a>
        @endif

        <div class="fi-page-header-titles">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE, scopes: $this->getRenderHookScopes()) }}

            @if (filled($displayTitle))
                <h1 class="fi-header-heading">
                    {{ $displayTitle }}
                </h1>
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_HEADING_AFTER, scopes: $this->getRenderHookScopes()) }}

            @if (filled($subheading))
                <p class="fi-header-subheading">
                    {{ $subheading }}
                </p>
            @endif
        </div>
    </div>

    @php
        $beforeActions = \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, scopes: $this->getRenderHookScopes());
        $afterActions = \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, scopes: $this->getRenderHookScopes());
    @endphp

    @if (filled($beforeActions) || $actions || filled($afterActions))
        <div class="fi-header-actions-ctn">
            {{ $beforeActions }}

            @if ($actions)
                <x-filament::actions
                    :actions="$actions"
                    :alignment="$actionsAlignment"
                />
            @endif

            {{ $afterActions }}
        </div>
    @endif
</header>
