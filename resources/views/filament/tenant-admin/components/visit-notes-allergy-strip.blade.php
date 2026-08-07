@if ($patient?->hasClinicalWarnings())
    <div class="cs-modal-warnings">
        @if (filled($patient->allergies))
            <div class="cs-modal-warning">
                <div class="cs-modal-warning__label">{{ __('Allergies') }}</div>
                <div class="cs-modal-warning__value">{{ $patient->allergies }}</div>
            </div>
        @endif
        @if (filled($patient->conditions))
            <div class="cs-modal-warning">
                <div class="cs-modal-warning__label">{{ __('Ongoing conditions') }}</div>
                <div class="cs-modal-warning__value">{{ $patient->conditions }}</div>
            </div>
        @endif
        @if (filled($patient->medicines))
            <div class="cs-modal-warning">
                <div class="cs-modal-warning__label">{{ __('Current medicines') }}</div>
                <div class="cs-modal-warning__value">{{ $patient->medicines }}</div>
            </div>
        @endif
    </div>
@endif