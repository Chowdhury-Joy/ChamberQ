@php
    /*
     * 1:1 from public/previews/clireo-homepage.html hero.
     * Only difference: right side is a real POST form into /book.
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

    $doctorList = collect($doctors ?? [])->values();
    $sessionList = collect($sessions ?? [])->values();
    $chamberList = collect($chambers ?? [])->filter()->values();
    if ($chamberList->isEmpty()) {
        $chamberList = $sessionList->pluck('chamber')->filter()->unique('id')->values();
    }
    $showHeroForm = ($bookingAvailable ?? false) && $doctorList->isNotEmpty() && $sessionList->isNotEmpty();
    $needsChamberPick = $chamberList->count() > 1;
    $chamberOpenDays = $sessionList
        ->groupBy('chamber_id')
        ->map(fn ($rows) => $rows->pluck('day_of_week')->unique()->sort()->values()
            ->map(fn ($day) => DayOfWeek::label((int) $day))
            ->filter()
            ->implode(', '));
    $today = now()->toDateString();
    $maxDate = \App\Models\PlatformSetting::onlineBookingMaxDate();
    $sittingWeekdays = $sessionList->pluck('day_of_week')->unique()->filter(fn ($day) => $day !== null);
    $heroDateOptions = collect();
    $dateCursor = \Carbon\Carbon::parse($today)->startOfDay();
    $dateEnd = \Carbon\Carbon::parse($maxDate)->startOfDay();
    while ($dateCursor->lte($dateEnd)) {
        if ($sittingWeekdays->contains($dateCursor->dayOfWeek)) {
            $heroDateOptions->push([
                'value' => $dateCursor->toDateString(),
                'label' => $dateCursor->translatedFormat('D j M'),
                'day' => $dateCursor->dayOfWeek,
            ]);
        }
        $dateCursor->addDay();
    }

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
    <div class="hero-bg" @if(filled($image)) style="--hero-photo: url({{ json_encode($image, JSON_UNESCAPED_SLASHES) }});" @endif aria-hidden="true"></div>
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
            <form class="book-card space-card hero-media" id="book" method="post" action="{{ tenant_web_url('/book') }}" data-reveal-block data-reveal-kind="fade" data-closed-day="{{ __('This doctor is not sitting that day.') }}" data-open-days="{{ __('Sits: :days') }}">
                @csrf
                <h2>{{ $ctaText }}</h2>
                @if($needsChamberPick)
                    <div class="field">
                        <label for="hero-chamber">{{ __('Which centre?') }}*</label>
                        <select id="hero-chamber" name="chamber" required aria-label="{{ __('Which centre?') }}">
                            <option value="" disabled selected>{{ __('Select centre') }}</option>
                            @foreach($chamberList as $chamber)
                                <option value="{{ $chamber->id }}" data-open-days="{{ $chamberOpenDays[$chamber->id] ?? '' }}">{{ $chamber->name }}</option>
                            @endforeach
                        </select>
                        <small id="hero-chamber-hint" class="field-hint" hidden></small>
                    </div>
                @elseif($chamberList->isNotEmpty())
                    <input type="hidden" name="chamber" value="{{ $chamberList->first()->id }}">
                @endif
                <div class="field">
                    <label for="hero-phone">{{ __('Phone') }}*</label>
                    <input id="hero-phone" name="phone" type="tel" required inputmode="numeric" autocomplete="tel" placeholder="017XXXXXXXX">
                </div>
                <div class="field">
                    <label for="hero-patient-name">{{ __('Full Name') }}*</label>
                    <input id="hero-patient-name" name="name" type="text" required autocomplete="name" placeholder="Rahim Ahmed">
                </div>
                <div class="field">
                    <label for="hero-doctor">{{ __('Doctor') }}*</label>
                    <select id="hero-doctor" name="doctor" required>
                        @if($doctorList->count() > 1)
                            <option value="" disabled selected>{{ __('Select doctor') }}</option>
                        @endif
                        @foreach($doctorList as $doctor)
                            <option value="{{ $doctor->id }}" @selected($doctorList->count() === 1)>{{ $doctor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>{{ __('Date') }} &amp; {{ __('Session') }}*</label>
                    <div class="field-row">
                        <select id="hero-date" name="date" required aria-label="{{ __('Select date') }}">
                            <option value="" disabled selected>{{ __('Select date') }}</option>
                            @foreach($heroDateOptions as $heroDate)
                                <option value="{{ $heroDate['value'] }}" data-day="{{ $heroDate['day'] }}">{{ $heroDate['label'] }}</option>
                            @endforeach
                        </select>
                        <select id="hero-session" name="session" required aria-label="{{ __('Select session') }}">
                            <option value="" disabled selected>{{ __('Select session') }}</option>
                            @foreach($sessionList as $session)
                                @php
                                    $start = \Carbon\Carbon::parse($session->start_time)->format('g:i A');
                                    $end = \Carbon\Carbon::parse($session->end_time)->format('g:i A');
                                    $day = DayOfWeek::label($session->day_of_week);
                                @endphp
                                <option value="{{ $session->id }}" data-doctor="{{ $session->doctor_id }}" data-chamber="{{ $session->chamber_id }}" data-day="{{ $session->day_of_week }}">
                                    {{ $session->session_name }} · {{ $day }} {{ $start }}–{{ $end }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <small id="hero-session-hint" class="field-hint" hidden></small>
                </div>
                <button class="btn-pink" type="submit">
                    <span>{{ $ctaText }}</span>
                </button>
            </form>
            <script>
                (function () {
                    const form = document.getElementById('book');
                    const chamberSelect = document.getElementById('hero-chamber');
                    const doctorSelect = document.getElementById('hero-doctor');
                    const sessionSelect = document.getElementById('hero-session');
                    const dateSelect = document.getElementById('hero-date');
                    const chamberHint = document.getElementById('hero-chamber-hint');
                    const sessionHint = document.getElementById('hero-session-hint');
                    if (!form || !sessionSelect || !dateSelect) return;
                    const options = [...sessionSelect.options].filter((o) => o.value !== '');
                    const hiddenChamber = form.querySelector('input[name="chamber"][type="hidden"]');
                    const closedDay = form.dataset.closedDay || '';
                    const openDaysTpl = form.dataset.openDays || ':days';
                    const dateOptions = [...dateSelect.options].filter((o) => o.value !== '');

                    function selectedChamberId() {
                        if (chamberSelect) return chamberSelect.value;
                        return hiddenChamber ? hiddenChamber.value : '';
                    }

                    function dateDow() {
                        if (!dateSelect.value) return null;
                        const parts = dateSelect.value.split('-');
                        if (parts.length !== 3) return null;
                        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])).getDay();
                    }

                    function sittingDays() {
                        const chamberId = selectedChamberId();
                        const doctorId = doctorSelect ? doctorSelect.value : '';
                        const days = new Set();
                        options.forEach((option) => {
                            const chamberOk = !chamberId || option.dataset.chamber === String(chamberId);
                            const doctorOk = !doctorId || option.dataset.doctor === String(doctorId);
                            if (chamberOk && doctorOk) days.add(Number(option.dataset.day));
                        });
                        return days;
                    }

                    function filterDates() {
                        const ready = chamberReady();
                        const days = sittingDays();
                        dateOptions.forEach((option) => {
                            const match = ready && days.has(Number(option.dataset.day));
                            option.hidden = !match;
                            option.disabled = !match;
                        });
                        const currentOk = dateOptions.find((option) => option.value === dateSelect.value && !option.disabled);
                        dateSelect.value = currentOk ? currentOk.value : '';
                    }

                    function setHint(el, text) {
                        if (!el) return;
                        if (!text) {
                            el.hidden = true;
                            el.textContent = '';
                            return;
                        }
                        el.hidden = false;
                        el.textContent = text;
                    }

                    function chamberReady() {
                        return !chamberSelect || Boolean(chamberSelect.value);
                    }

                    function syncSessions() {
                        const ready = chamberReady();
                        dateSelect.disabled = !ready;
                        sessionSelect.disabled = !ready;

                        const chamberId = selectedChamberId();
                        const doctorId = doctorSelect ? doctorSelect.value : '';
                        const dow = dateDow();
                        let firstVisible = null;
                        let matchCount = 0;

                        options.forEach((option) => {
                            const chamberOk = !chamberId || option.dataset.chamber === String(chamberId);
                            const doctorOk = !doctorId || option.dataset.doctor === String(doctorId);
                            const dayOk = dow === null || Number(option.dataset.day) === dow;
                            const match = ready && chamberOk && doctorOk && dayOk;
                            option.hidden = !match;
                            option.disabled = !match;
                            if (match) {
                                matchCount += 1;
                                if (!firstVisible) firstVisible = option;
                            }
                        });

                        const currentOk = options.find((o) => o.value === sessionSelect.value && !o.disabled);
                        // Do not auto-pick a sitting until a date is chosen — same
                        // empty-state rule as the date list (native calendars looked booked).
                        sessionSelect.value = currentOk
                            ? currentOk.value
                            : (dow !== null && firstVisible ? firstVisible.value : '');

                        if (chamberSelect && chamberSelect.selectedOptions[0]) {
                            const days = chamberSelect.selectedOptions[0].dataset.openDays || '';
                            setHint(chamberHint, days ? openDaysTpl.replace(':days', days) : '');
                        } else {
                            setHint(chamberHint, '');
                        }

                        if (ready && chamberId && dow !== null && matchCount === 0) {
                            setHint(sessionHint, closedDay);
                        } else {
                            setHint(sessionHint, '');
                        }
                    }

                    function onChamberOrDoctorChange() {
                        filterDates();
                        syncSessions();
                    }

                    if (chamberSelect) chamberSelect.addEventListener('change', onChamberOrDoctorChange);
                    if (doctorSelect) doctorSelect.addEventListener('change', onChamberOrDoctorChange);
                    dateSelect.addEventListener('change', syncSessions);
                    onChamberOrDoctorChange();
                })();
            </script>
        @endif
    </div>
</section>
