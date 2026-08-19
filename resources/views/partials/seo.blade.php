@php
    /** @var array<string, mixed> $seo */
    $seo = $seo ?? [];
@endphp
@if(! empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
@endif
@if(! empty($seo['robots']))
    <meta name="robots" content="{{ $seo['robots'] }}">
@endif
@if(! empty($seo['canonical']))
    <link rel="canonical" href="{{ $seo['canonical'] }}">
@endif
@if(! empty($seo['title']))
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
@endif
@if(! empty($seo['description']))
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
@endif
@if(! empty($seo['canonical']))
    <meta property="og:url" content="{{ $seo['canonical'] }}">
@endif
<meta property="og:type" content="{{ $seo['ogType'] ?? 'website' }}">
<meta property="og:locale" content="{{ $seo['locale'] ?? 'en' }}">
@if(! empty($seo['siteName']))
    <meta property="og:site_name" content="{{ $seo['siteName'] }}">
@endif
@if(! empty($seo['image']))
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
@else
    <meta name="twitter:card" content="summary">
@endif
@foreach(($seo['jsonLd'] ?? []) as $graph)
    <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
