@props([
    'count',
    'tag' => 'div',
])

<{{ $tag }}
    {{ $attributes->class(['card-grid'])->merge(['data-card-count' => (string) $count]) }}
>
    {{ $slot }}
</{{ $tag }}>
