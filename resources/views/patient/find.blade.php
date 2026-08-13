<x-patient.layout :title="__('Find a doctor')">
    <section class="pf-hero">
        <h1>{{ __('Find a doctor') }}</h1>
        <p>{{ __('Book a serial with any ChamberQ doctor. No login needed.') }}</p>
    </section>

    <form class="pf-search" method="GET" action="/find">
        <label class="pf-sr" for="q">{{ __('Search by name, specialty, or area') }}</label>
        <input id="q" type="search" name="q" value="{{ $query }}" placeholder="{{ __('Search by name, specialty, or area') }}">
        <label class="pf-sr" for="specialty">{{ __('Specialty') }}</label>
        <select id="specialty" name="specialty">
            <option value="">{{ __('All specialties') }}</option>
            @foreach($specialties as $value => $label)
                <option value="{{ $value }}" @selected($specialty === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="mk-btn mk-btn-primary">{{ __('Search') }}</button>
    </form>

    @if($listings->isEmpty())
        <p class="pf-empty">{{ __('No doctors are taking online serials right now.') }}</p>
    @else
        <x-card-grid :count="$listings->count()" class="pf-cards">
            @foreach($listings as $card)
                <article class="pf-card">
                    @if($card['photo_url'])
                        <img class="pf-card-photo" src="{{ $card['photo_url'] }}" alt="">
                    @endif
                    <h2>{{ $card['name'] }}</h2>
                    <p class="pf-card-spec">{{ $card['specialty'] }}</p>
                    @if($card['qualifications'])
                        <p class="pf-card-qual">{{ $card['qualifications'] }}</p>
                    @endif
                    <p class="pf-card-clinic">{{ $card['clinic'] }}</p>
                    @foreach($card['chambers'] as $chamber)
                        <p class="pf-card-chamber">
                            {{ $chamber['name'] }}
                            @if($chamber['address'])
                                <span>{{ $chamber['address'] }}</span>
                            @endif
                        </p>
                    @endforeach
                    @if($card['fee_label'])
                        <p class="pf-card-fee">{{ $card['fee_label'] }}</p>
                    @endif
                    <a class="mk-btn mk-btn-primary pf-card-cta" href="{{ $card['book_url'] }}">{{ __('Book serial') }}</a>
                </article>
            @endforeach
        </x-card-grid>
    @endif
</x-patient.layout>
