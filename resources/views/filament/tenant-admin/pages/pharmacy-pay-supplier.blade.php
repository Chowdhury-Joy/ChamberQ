<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border p-4">
            <p class="text-xs uppercase text-gray-500">{{ __('Owed to suppliers') }}</p>
            <p class="text-2xl font-semibold">৳{{ number_format($balance['owed']) }}</p>
        </div>
        <div class="rounded-xl border p-4">
            <p class="text-xs uppercase text-gray-500">{{ __('Refund due from suppliers') }}</p>
            <p class="text-2xl font-semibold">৳{{ number_format($balance['refund_due']) }}</p>
        </div>
        <div class="rounded-xl border p-4">
            <p class="text-xs uppercase text-gray-500">{{ __('Owed to doctors (pharmacy cut)') }}</p>
            <p class="text-2xl font-semibold">৳{{ number_format($balance['doctor_pending']) }}</p>
        </div>
    </div>
</x-filament-panels::page>
