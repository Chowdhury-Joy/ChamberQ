<x-patient.layout :title="__('My serials')">
    <section class="pf-hero">
        <h1>{{ __('My serials') }}</h1>
        <p>{{ __('Upcoming visits across every ChamberQ doctor for :phone', ['phone' => $account->phone]) }}</p>
    </section>

    <p class="pf-tabs">
        <a class="is-active" href="/me">{{ __('My serials') }}</a>
        <a href="/me/history">{{ __('History') }}</a>
    </p>

    @if($serials->isEmpty())
        <p class="pf-empty">{{ __('No upcoming serials.') }}</p>
        <p class="pf-empty"><a href="/find">{{ __('Find a doctor') }}</a></p>
    @else
        <ul class="pf-list">
            @foreach($serials as $row)
                <li class="pf-list-card">
                    <p class="pf-list-kicker">{{ $row['patient_name'] }} · {{ __('Serial') }} {{ $row['serial_number'] }}</p>
                    <h2>{{ $row['doctor_name'] ?: $row['session_name'] }}</h2>
                    <p>{{ $row['booking_date'] }}@if($row['session_name']) · {{ $row['session_name'] }}@endif</p>
                    <a href="{{ $row['ticket_url'] }}">{{ __('View ticket') }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-patient.layout>
