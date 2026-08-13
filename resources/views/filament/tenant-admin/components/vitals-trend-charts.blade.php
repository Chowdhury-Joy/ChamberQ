@php
    use App\Support\VitalsTrend;

    $trend = $trend ?? ['weight' => [], 'systolic' => [], 'diastolic' => []];
    $weightChart = VitalsTrend::lineChart($trend['weight'] ?? []);
    $systolicChart = VitalsTrend::lineChart($trend['systolic'] ?? []);
    $diastolicChart = VitalsTrend::lineChart($trend['diastolic'] ?? []);
    $showBp = $systolicChart !== null && $diastolicChart !== null;
@endphp

@if ($weightChart || $showBp)
    <div class="cs-vitals-trends" style="display: grid; gap: 0.75rem; margin-top: 0.75rem;">
        @if ($weightChart)
            <div>
                <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--gray-500); margin-bottom: 0.25rem;">
                    {{ __('Weight trend') }}
                </div>
                <svg
                    viewBox="0 0 {{ $weightChart['width'] }} {{ $weightChart['height'] }}"
                    width="100%"
                    height="{{ $weightChart['height'] }}"
                    role="img"
                    aria-label="{{ __('Weight trend chart') }}"
                    style="display: block; max-width: 100%;"
                >
                    <path d="{{ $weightChart['path'] }}" fill="none" stroke="currentColor" stroke-width="1.75" style="color: var(--primary-600);"></path>
                </svg>
                <div style="display: flex; justify-content: space-between; font-size: 0.65rem; color: var(--gray-500); margin-top: 0.15rem;">
                    <span>{{ $weightChart['labels'][0] ?? '' }}</span>
                    <span>{{ number_format($weightChart['min'], 1) }}–{{ number_format($weightChart['max'], 1) }} kg</span>
                    <span>{{ $weightChart['labels'][array_key_last($weightChart['labels'])] ?? '' }}</span>
                </div>
            </div>
        @endif

        @if ($showBp)
            <div>
                <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--gray-500); margin-bottom: 0.25rem;">
                    {{ __('Blood pressure trend') }}
                </div>
                <svg
                    viewBox="0 0 {{ $systolicChart['width'] }} {{ $systolicChart['height'] }}"
                    width="100%"
                    height="{{ $systolicChart['height'] }}"
                    role="img"
                    aria-label="{{ __('Blood pressure trend chart') }}"
                    style="display: block; max-width: 100%;"
                >
                    <path d="{{ $systolicChart['path'] }}" fill="none" stroke="currentColor" stroke-width="1.75" style="color: var(--danger-600);"></path>
                    <path d="{{ $diastolicChart['path'] }}" fill="none" stroke="currentColor" stroke-width="1.75" stroke-dasharray="4 3" style="color: var(--info-600);"></path>
                </svg>
                <div style="display: flex; justify-content: space-between; font-size: 0.65rem; color: var(--gray-500); margin-top: 0.15rem;">
                    <span>{{ $systolicChart['labels'][0] ?? '' }}</span>
                    <span>{{ __('Sys') }} / {{ __('Dia') }}</span>
                    <span>{{ $systolicChart['labels'][array_key_last($systolicChart['labels'])] ?? '' }}</span>
                </div>
            </div>
        @endif
    </div>
@endif
