<div class="space-y-3 text-sm" x-data>
    <p class="text-lg font-semibold">{{ __('Pharmacy receipt') }}</p>
    <p>{{ $sale->patient_name ?: __('Walk-in') }} · {{ $sale->occurred_on?->toDateString() }}</p>
    <ul class="space-y-1">
        @foreach ($sale->items as $line)
            <li>{{ $line->name }} × {{ $line->qty }} — ৳{{ number_format($line->line_total_taka) }}</li>
        @endforeach
    </ul>
    <p class="font-semibold">
        @if ($sale->waived)
            {{ __('Waived') }}
        @else
            {{ __('Total') }}: ৳{{ number_format($sale->amount) }}
        @endif
    </p>
    <button type="button" class="fi-btn fi-size-sm" onclick="window.print()">{{ __('Print') }}</button>
</div>
