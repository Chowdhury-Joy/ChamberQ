<section class="mk-section mk-value" id="why" aria-labelledby="why-heading">
    <div class="mk-wrap mk-value-layout">
        <div class="mk-section-head">
            <div>
                <p class="mk-kicker">What really changes</p>
                <h2 id="why-heading">Good care feels better<br><em>when the wait does too.</em></h2>
            </div>
            <p>Less admin for you. More respect for their time. A visit worth telling others about.</p>
        </div>
        <ul class="mk-value-list">
            @foreach(config('marketing.value_points') as $point)
                @php
                    $exists = file_exists(public_path($point['image']));
                    $featured = !empty($point['featured']);
                @endphp
                <li class="mk-value-item {{ $featured ? 'is-main' : '' }}">
                    <div class="mk-value-thumb">
                        @if($exists)
                            <img src="{{ asset($point['image']) }}" alt="{{ $point['title'] }}" width="640" height="400">
                        @else
                            <div class="mk-value-art" aria-hidden="true">
                                @if($loop->iteration === 1)
                                    <span class="mk-art-phone">☎</span><span class="mk-art-slash"></span><span class="mk-art-clock">25m</span>
                                @elseif($loop->iteration === 2)
                                    <span class="mk-art-person"></span><span class="mk-art-progress"><i></i></span><b>08</b>
                                @else
                                    <span class="mk-art-bubble">“That was easy.”</span><span class="mk-art-hearts">♥ ♥</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="mk-value-body">
                        <h3>{{ $point['title'] }}</h3>
                        <p>{{ $point['caption'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
