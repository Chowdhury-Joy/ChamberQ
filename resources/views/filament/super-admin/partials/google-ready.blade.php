@php
    $items = $items ?? [];
    $ready = count(array_filter($items, fn (array $item): bool => $item['ok']));
    $total = count($items);
@endphp

@if($items !== [])
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="mb-3 font-medium text-gray-950 dark:text-white">
            {{ __('Google-ready') }} — {{ $ready }}/{{ $total }}
        </p>
        <ul class="space-y-2">
            @foreach($items as $item)
                <li>
                    <span @class([
                        'font-medium',
                        'text-success-600 dark:text-success-400' => $item['ok'],
                        'text-danger-600 dark:text-danger-400' => ! $item['ok'],
                    ])>
                        {{ $item['ok'] ? '✓' : '○' }} {{ $item['label'] }}
                    </span>
                    @if(! $item['ok'])
                        <span class="mt-0.5 block text-xs text-gray-500">{{ $item['hint'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-gray-500">
            {{ __('This list does not block save. Tick the gaps before you tell a doctor their site is live on Google.') }}
        </p>
    </div>
@endif
