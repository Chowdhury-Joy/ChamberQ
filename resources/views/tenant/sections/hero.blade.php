@php
    /*
     * 1:1 from public/previews/clireo-homepage.html hero.
     * Only difference: right side is a real GET form into /book.
     */
    use App\Support\DayOfWeek;

    $tenant = $tenant ?? tenant();
    $headline = $data['headline'] ?? 'Where every recovery matters';
    $subheadline = $data['subheadline'] ?? 'Specialized physiotherapy and rehabilitation — stroke recovery, chronic pain, paralysis, sports injuries, and neuromuscular care.';
    $ctaText = $data['cta_text'] ?? 'Book appointment';
    $backedLead = $data['backed_lead'] ?? 'Backed by';
    $backedStrong = $data['backed_strong'] ?? '8+ specialists';
    $ratingScore = $data['rating_score'] ?? '4.9*';
    $ratingCopy = $data['rating_copy'] ?? 'Patients trust our recovery-focused care!';
    $image = $data['image_url'] ?? null;

    $doctorList = ($doctors ?? collect())->values();
    $sessionList = ($sessions ?? collect())->values();
    $showHeroForm = ($bookingAvailable ?? false) && $doctorList->isNotEmpty() && $sessionList->isNotEmpty();
    $today = now()->toDateString();
    $maxDate = now()->addDays(60)->toDateString();

    $backedAvatars = [
        $data['backed_avatar_1'] ?? 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=80&h=80&q=80',
        $data['backed_avatar_2'] ?? 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=80&h=80&q=80',
        $data['backed_avatar_3'] ?? 'https://images.unsplash.com/photo-1594824476967-48c8b964273f?auto=format&fit=crop&w=80&h=80&q=80',
    ];
    $ratingAvatars = [
        $data['rating_avatar_1'] ?? 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=80&h=80&q=80',
        $data['rating_avatar_2'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&h=80&q=80',
        $data['rating_avatar_3'] ?? 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=80&h=80&q=80',
    ];
@endphp

<section class="hero space-inline" data-reveal-section>
    <div class="hero-bg" @if(filled($image)) style="background-image: linear-gradient(90deg, color-mix(in srgb, var(--ink-deep) 92%, transparent) 0%, color-mix(in srgb, var(--ink) 72%, transparent) 42%, color-mix(in srgb, var(--ink) 15%, transparent) 100%), url('{{ $image }}');" @endif aria-hidden="true"></div>
    <div class="layout-container grid-hero">
        <div class="hero-copy">
            <div class="backed" data-reveal-block data-reveal-kind="fade">
                <div class="backed-avs">
                    @foreach($backedAvatars as $avatar)
                        <img src="{{ $avatar }}" alt="">
                    @endforeach
                </div>
                <span>{{ $backedLead }} <strong>{{ $backedStrong }}</strong></span>
            </div>

            <h1 class="hero-title fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading" aria-label="{{ $headline }}">{{ $headline }}</h1>

            <p class="hero-lead" data-reveal-block data-reveal-kind="fade">{{ $subheadline }}</p>

            <div class="rating-row" data-reveal-block data-reveal-kind="fade">
                <div class="rating-score">{{ $ratingScore }}</div>
                <div class="rating-avs">
                    @foreach($ratingAvatars as $avatar)
                        <img src="{{ $avatar }}" alt="">
                    @endforeach
                    <span class="rating-plus">+</span>
                </div>
                <div class="rating-copy">{{ $ratingCopy }}</div>
            </div>
        </div>

        @if($showHeroForm)
            <form class="book-card space-card hero-media" id="book" method="get" action="{{ tenant_web_url('/book') }}" data-reveal-block data-reveal-kind="fade">
                <h2>{{ $ctaText }}</h2>
                <div class="field">
                    <label for="hero-patient-name">{{ __('Full Name') }}*</label>
                    <input id="hero-patient-name" name="name" type="text" required autocomplete="name" placeholder="Rahim Ahmed">
                </div>
                <div class="field">
                    <label>{{ __('Phone') }} &amp; {{ __('Doctor') }}*</label>
                    <div class="field-row">
                        <input id="hero-phone" name="phone" type="tel" required inputmode="numeric" autocomplete="tel" placeholder="017XXXXXXXX" aria-label="{{ __('Phone') }}">
                        <select id="hero-doctor" name="doctor" required aria-label="{{ __('Select doctor') }}">
                            @if($doctorList->count() > 1)
                                <option value="" disabled selected>{{ __('Select doctor') }}</option>
                            @endif
                            @foreach($doctorList as $doctor)
                                <option value="{{ $doctor->id }}" @selected($doctorList->count() === 1)>{{ $doctor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label>{{ __('Date') }} &amp; {{ __('Session') }}*</label>
                    <div class="field-row">
                        <input id="hero-date" name="date" type="date" required min="{{ $today }}" max="{{ $maxDate }}" aria-label="{{ __('Date') }}">
                        <select id="hero-session" name="session" required aria-label="{{ __('Select session') }}">
                            <option value="" disabled selected>{{ __('Select session') }}</option>
                            @foreach($sessionList as $session)
                                @php
                                    $start = \Carbon\Carbon::parse($session->start_time)->format('g:i A');
                                    $end = \Carbon\Carbon::parse($session->end_time)->format('g:i A');
                                    $day = DayOfWeek::label($session->day_of_week);
                                @endphp
                                <option value="{{ $session->id }}" data-doctor="{{ $session->doctor_id }}">
                                    {{ $session->session_name }} · {{ $day }} {{ $start }}–{{ $end }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button class="btn-pink" type="submit">
                    <span>{{ $ctaText }}</span>
                </button>
            </form>
            <script>
                (function () {
                    const doctorSelect = document.getElementById('hero-doctor');
                    const sessionSelect = document.getElementById('hero-session');
                    if (!doctorSelect || !sessionSelect) return;
                    const options = [...sessionSelect.options].filter((o) => o.value !== '');
                    function syncSessions() {
                        const doctorId = doctorSelect.value;
                        let firstVisible = null;
                        options.forEach((option) => {
                            const match = !doctorId || option.dataset.doctor === doctorId;
                            option.hidden = !match;
                            option.disabled = !match;
                            if (match && !firstVisible) firstVisible = option;
                        });
                        sessionSelect.value = firstVisible ? firstVisible.value : '';
                    }
                    doctorSelect.addEventListener('change', syncSessions);
                    syncSessions();
                })();
            </script>
        @endif
    </div>
</section>
