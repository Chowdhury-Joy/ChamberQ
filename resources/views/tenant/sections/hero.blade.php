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

    /*
     * Avatars are opt-in, not defaulted.
     *
     * These used to fall back to six hardcoded Unsplash photos. On a live
     * clinic site that presents photographs of real, unrelated people as "our
     * specialists" and "our patients" — a claim the clinic cannot stand behind,
     * hotlinked from a third party on every patient visit. A clinic that has
     * not uploaded photos now gets the initials treatment instead.
     */
    $backedAvatars = array_values(array_filter([
        $data['backed_avatar_1'] ?? null,
        $data['backed_avatar_2'] ?? null,
        $data['backed_avatar_3'] ?? null,
    ]));
    $ratingAvatars = array_values(array_filter([
        $data['rating_avatar_1'] ?? null,
        $data['rating_avatar_2'] ?? null,
        $data['rating_avatar_3'] ?? null,
    ]));
@endphp

<section class="hero space-inline" data-reveal-section>
    <div class="hero-bg" @if(filled($image)) style="background-image: linear-gradient(90deg, color-mix(in srgb, var(--ink-deep) 92%, transparent) 0%, color-mix(in srgb, var(--ink) 72%, transparent) 42%, color-mix(in srgb, var(--ink) 15%, transparent) 100%), url('{{ $image }}');" @endif aria-hidden="true"></div>
    <div class="layout-container grid-hero">
        <div class="hero-copy">
            <div class="backed" data-reveal-block data-reveal-kind="fade">
                @if($backedAvatars !== [])
                    <div class="backed-avs">
                        @foreach($backedAvatars as $avatar)
                            <img src="{{ $avatar }}" alt="">
                        @endforeach
                    </div>
                @endif
                <span>{{ $backedLead }} <strong>{{ $backedStrong }}</strong></span>
            </div>

            <h1 class="hero-title fx-heading" data-fx-words data-reveal-block data-reveal-kind="heading" aria-label="{{ $headline }}">{{ $headline }}</h1>

            <p class="hero-lead" data-reveal-block data-reveal-kind="fade">{{ $subheadline }}</p>

            <div class="rating-row" data-reveal-block data-reveal-kind="fade">
                <div class="rating-score">{{ $ratingScore }}</div>
                @if($ratingAvatars !== [])
                    <div class="rating-avs">
                        @foreach($ratingAvatars as $avatar)
                            <img src="{{ $avatar }}" alt="">
                        @endforeach
                        <span class="rating-plus">+</span>
                    </div>
                @endif
                <div class="rating-copy">{{ $ratingCopy }}</div>
            </div>
        </div>

        @if($showHeroForm)
            {{-- POST, not GET: a GET here put the patient's name and phone in
                 the address bar, browser history and every access log on the
                 way. The controller flashes them to the session and redirects. --}}
            <form class="book-card space-card hero-media" id="book" method="post" action="{{ tenant_web_url('/book') }}" data-reveal-block data-reveal-kind="fade">
                @csrf
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
