@php
    use App\Support\RxSafety;

    $allergies = $allergies ?? null;
    $items = is_callable($items ?? null) ? ($items)() : ($items ?? []);
    $warnings = RxSafety::allWarnings($allergies, is_array($items) ? $items : []);
@endphp

@if ($warnings !== [])
    <div
        class="cs-rx-safety"
        style="margin-bottom: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid var(--warning-300); background: color-mix(in srgb, var(--warning-50) 80%, transparent);"
    >
        <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--warning-800); margin-bottom: 0.35rem;">
            {{ __('Prescription checks') }}
        </div>
        <ul style="margin: 0; padding-left: 1.1rem; font-size: 0.875rem; color: var(--warning-950);">
            @foreach ($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
        {{-- Never drop this. It is what tells the doctor how much weight these
             warnings carry, in place of a named clinical reviewer. --}}
        <div style="margin-top: 0.4rem; font-size: 0.75rem; color: var(--warning-800);">
            {{ __(RxSafety::DISCLAIMER) }}
        </div>
    </div>
@endif
