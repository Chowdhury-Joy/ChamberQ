<x-patient.layout :title="__('History')">
    <section class="pf-hero">
        <h1>{{ __('History') }}</h1>
        <p>{{ __('Past visits and prescriptions for :phone', ['phone' => $account->phone]) }}</p>
    </section>

    <p class="pf-tabs">
        <a href="/me">{{ __('My serials') }}</a>
        <a class="is-active" href="/me/history">{{ __('History') }}</a>
    </p>

    @if($visits->isEmpty())
        <p class="pf-empty">{{ __('No past visits yet.') }}</p>
    @else
        <ul class="pf-list">
            @foreach($visits as $row)
                <li class="pf-list-card">
                    <p class="pf-list-kicker">{{ $row['patient_name'] }} · {{ $row['booking_date'] }}</p>
                    <h2>{{ $row['doctor_name'] ?: $row['session_name'] }}</h2>
                    @if($row['diagnosis'])
                        <p>{{ $row['diagnosis'] }}</p>
                    @endif
                    @if($row['medicines'])
                        <p class="pf-meds">{{ implode(', ', $row['medicines']) }}</p>
                    @endif
                    @if($row['prescription_id'])
                        <a href="{{ url('/me/prescriptions/'.$row['prescription_id']) }}">{{ __('View prescription') }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-patient.layout>
