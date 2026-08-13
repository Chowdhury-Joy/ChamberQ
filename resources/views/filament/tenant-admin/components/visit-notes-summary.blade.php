@php
    $prescription = $record->prescription;
    $items = $prescription?->items ?? collect();
@endphp

<div class="cs-summary-panel">
    <div class="cs-summary-panel__title">{{ __('Ready to complete') }}</div>
    <p class="cs-summary-panel__hint">{{ __('Everything below is already saved. Tap Complete to close the visit, or Edit to change anything.') }}</p>

    @if ($record->diagnosisLabel())
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('Diagnosis') }}</span>
            <span>{{ $record->diagnosisLabel() }}</span>
        </div>
    @endif

    @if ($record->vitalsSummary())
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('Vitals') }}</span>
            <span>{{ $record->vitalsSummary() }}</span>
        </div>
    @endif

    @if (filled($record->chief_complaint))
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('C/C') }}</span>
            <span>{{ $record->chief_complaint }}</span>
        </div>
    @endif

    @if (filled($record->history))
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('H/O') }}</span>
            <span>{{ $record->history }}</span>
        </div>
    @endif

    @if (filled($record->on_examination))
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('O/E') }}</span>
            <span>{{ $record->on_examination }}</span>
        </div>
    @endif

    @if (filled($record->clinical_notes))
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('Clinical notes') }}</span>
            <span>{{ $record->clinical_notes }}</span>
        </div>
    @endif

    @if ($items->isNotEmpty())
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('Medicines') }}</span>
            <span>{{ trans_choice(':count medicine|:count medicines', $items->count(), ['count' => $items->count()]) }}</span>
        </div>
        <ul class="cs-summary-panel__list">
            @foreach ($items as $item)
                <li>{{ $item->medicine_name }} — {{ collect([$item->dose, $item->frequency, $item->duration, $item->timingLabel()])->filter()->implode(', ') }}</li>
            @endforeach
        </ul>
    @endif

    @if (filled($record->advice))
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('Advice') }}</span>
            <span>{{ $record->advice }}</span>
        </div>
    @endif

    @if (filled($record->tests_advised))
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('Inv') }}</span>
            <span>{{ $record->tests_advised }}</span>
        </div>
    @endif

    @if ($record->followUpLabel())
        <div class="cs-summary-panel__row">
            <span class="cs-summary-panel__label">{{ __('Follow-up') }}</span>
            <span>{{ $record->followUpLabel() }}</span>
        </div>
    @endif
</div>
