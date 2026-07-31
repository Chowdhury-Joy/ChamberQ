<section class="mk-section" id="how" aria-labelledby="how-heading">
    <div class="mk-wrap">
        <div class="mk-section-head mk-section-head-split">
            <div>
                <p class="mk-kicker">One simple flow</p>
                <h2 id="how-heading">From “I need a doctor”<br>to <em>“I’m next.”</em></h2>
            </div>
            <p>Six small steps. No app download, no patient account, no confusing setup.</p>
        </div>
        <x-card-grid :count="count(config('marketing.steps'))">
            @foreach(config('marketing.steps') as $index => $step)
                @php
                    $exists = file_exists(public_path($step['image']));
                @endphp
                <article class="mk-step-card">
                    <div class="mk-step-thumb">
                        @if($exists)
                            <img src="{{ asset($step['image']) }}" alt="{{ $step['title'] }}" width="800" height="600">
                        @else
                            @include('marketing.partials.product-preview', ['preview' => $step['key']])
                        @endif
                    </div>
                    <div class="mk-step-body">
                        <span class="mk-step-num">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['caption'] }}</p>
                    </div>
                </article>
            @endforeach
        </x-card-grid>
    </div>
</section>
