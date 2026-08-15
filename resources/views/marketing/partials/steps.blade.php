<section class="mk-section" id="how" aria-labelledby="how-heading">
    <div class="mk-wrap">
        <div class="mk-section-head mk-section-head-split">
            <div>
                <p class="mk-kicker">One simple flow</p>
                <h2 id="how-heading">From your front desk<br>to <em>your consult.</em></h2>
            </div>
            <p>Six steps you actually run. We set it up with you. No app for you to learn.</p>
        </div>
        <x-card-grid :count="count($steps)">
            @foreach($steps as $index => $step)
                <article class="mk-step-card">
                    <div class="mk-step-thumb">
                        @if($step['image'])
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
