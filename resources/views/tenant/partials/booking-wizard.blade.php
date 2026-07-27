{{-- Shared booking wizard markup + JS. Shells: tenant.book / tenant.solo.book --}}
            @if(! $bookingAvailable)
                <div class="booking-header">
                    <h2>{{ __('Booking unavailable') }}</h2>
                    <p class="text-muted" style="margin-top: 1rem; line-height: 1.6;">
                        {{ __('This clinic has not published schedules yet. Please contact the clinic and try again later.') }}
                    </p>
                    @if(filled($tenant->contact_phone))
                        <p style="margin-top: 1.5rem;">
                            <a href="tel:{{ $tenant->contact_phone }}" class="btn" style="display: inline-block;">
                                {{ __('Call') }} {{ $tenant->contact_phone }}
                            </a>
                        </p>
                    @endif
                    <p style="margin-top: 1.5rem;">
                        <a href="/" class="btn btn-back" style="display: inline-block;">{{ __('Back to home') }}</a>
                    </p>
                </div>
            @else
            <div class="booking-header">
                <h2>{{ __('Book Your Appointment') }}</h2>
                <div class="progress-bar" id="progressBar"></div>
            </div>
            
            <div id="message" class="alert" role="alert" aria-live="polite"></div>

            <form id="bookingForm">
                @csrf
                
                <!-- STEP 1: Type Selection -->
                <div class="step" id="step-type" data-step-name="type">
                    <h3>{{ __('What would you like to book?') }}</h3>
                    <div class="selection-grid">
                        @if($canBookConsultation)
                        <div class="selection-card" onclick="selectType('session')">
                            <h4>{{ __('Doctor Consultation') }}</h4>
                            <p>{{ __('Book a visit with one of our specialists.') }}</p>
                        </div>
                        @endif
                        @if($hasLabTests && $canBookLab)
                        <div class="selection-card" onclick="selectType('lab')">
                            <h4>{{ __('Lab Tests') }}</h4>
                            <p>{{ __('Book pathology and imaging tests.') }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- STEP 2: Chamber Selection -->
                <div class="step" id="step-chamber" data-step-name="chamber">
                    <h3>{{ __('Select a Location') }}</h3>
                    <div class="selection-grid" id="chamber-grid">
                        @foreach($chambers as $chamber)
                        <div class="selection-card" onclick="selectChamber('{{ $chamber->id }}')">
                            <h4>{{ $chamber->name }}</h4>
                            <p>{{ $chamber->address }}</p>
                        </div>
                        @endforeach
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-back" onclick="prevStep()">{{ __('Back') }}</button>
                    </div>
                </div>

                <!-- STEP 3: Doctor Selection -->
                <div class="step" id="step-doctor" data-step-name="doctor">
                    <h3>{{ __('Select a Doctor') }}</h3>
                    <div class="selection-grid" id="doctor-grid">
                        @foreach($doctors as $doctor)
                        <div class="selection-card" onclick="selectDoctor('{{ $doctor->id }}')">
                            <h4>{{ $doctor->name }}</h4>
                            <p>{{ $doctor->specialty }}</p>
                        </div>
                        @endforeach
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-back" onclick="prevStep()">{{ __('Back') }}</button>
                    </div>
                </div>

                <!-- STEP 4: Session / Slot Selection -->
                <div class="step" id="step-session" data-step-name="session">
                    <h3 id="session-title">{{ __('Select a Schedule') }}</h3>
                    <div class="selection-grid list-view" id="session-grid">
                        <!-- Populated by JS -->
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-back" onclick="prevStep()">{{ __('Back') }}</button>
                    </div>
                </div>

                <!-- STEP 5: Lab Tests Selection -->
                <div class="step" id="step-lab-tests" data-step-name="lab-tests">
                    <h3>{{ __('Select Lab Tests') }}</h3>
                    <p class="text-muted" style="margin-bottom:1rem;">{{ __('You can select multiple tests.') }}</p>
                    <div class="selection-grid list-view">
                        @foreach($labTests as $test)
                        <label class="selection-card" style="display:block; padding-left:3rem; position:relative;">
                            <input type="checkbox" name="lab_tests[]" value="{{ $test->id }}" data-price="{{ $test->price }}" onchange="updateTotal()" style="position:absolute; left:1rem; top:1.5rem; transform:scale(1.5);">
                            <span class="price">৳{{ number_format($test->price, 2) }}</span>
                            <h4>{{ $test->test_name }}</h4>
                            <p>{{ $test->description }}</p>
                        </label>
                        @endforeach
                    </div>
                    <div class="lab-total">
                        {{ __('Total:') }} <span id="labTotalAmount">৳0.00</span>
                    </div>
                    <p class="lab-test-error" id="labTestError" role="alert" aria-live="polite">{{ __('Please select at least one lab test.') }}</p>
                    <div class="btn-group">
                        <button type="button" class="btn btn-back" onclick="prevStep()">{{ __('Back') }}</button>
                        <button type="button" class="btn btn-primary" onclick="continueLabTests()">{{ __('Continue') }}</button>
                    </div>
                </div>

                <!-- STEP 6: Identity & Date -->
                <div class="step" id="step-identity" data-step-name="identity">
                    <h3>{{ __('Patient Details') }}</h3>

                    <div class="booking-review" id="bookingReview" aria-live="polite">
                        <div id="reviewSummary"></div>
                        <div class="seats-line" id="reviewSeats"></div>
                        <div style="margin-top:0.35rem;color:#64748b;font-size:0.9rem;">{{ __('Pay at the clinic') }}</div>
                    </div>
                    
                    <div class="form-group" style="margin-top:1.5rem;">
                        <label class="form-label" for="booking_date">{{ __('Booking Date') }}</label>
                        <input type="date" name="booking_date" id="booking_date" class="form-control" required>
                        <small class="text-muted" id="date-helper" style="display:block;margin-top:0.5rem"></small>
                        <span class="field-error" id="date-error" role="alert" aria-live="polite" style="display:none"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="patient_name">{{ __('Patient Name') }}</label>
                        <input type="text" name="patient_name" id="patient_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="patient_phone">{{ __('Phone Number') }}</label>
                        <input type="tel" name="patient_phone" id="patient_phone" class="form-control" placeholder="017..." required>
                        <span class="field-error" id="phone-error" role="alert" aria-live="polite" style="display:none"></span>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-back" onclick="prevStep()">{{ __('Back') }}</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">{{ __('Confirm Booking') }}</button>
                    </div>
                </div>
            </form>
            
            <div id="successView" class="hidden" style="text-align: center;">
                <h3>{{ __('Booking Confirmed!') }}</h3>
                <p>{{ __('Your serial number is:') }}</p>
                <div style="font-size: 3rem; font-weight:700; color: var(--color-primary); margin: 1rem 0;" id="serialBadge"></div>
                <p style="line-height:1.5;margin:0 0 0.75rem;">{{ __('Show this serial at reception. Keep the next page open or save its link.') }}</p>
                <p class="text-muted" id="successTicketHint">{{ __('Opening your ticket…') }}</p>
                <p style="margin-top:1.25rem;">
                    <a href="#" class="btn btn-primary" id="openTicketNow" style="display:inline-block;">{{ __('Open ticket') }}</a>
                </p>
            </div>
            @endif
        </div>

    @if($bookingAvailable)
    <!-- Data passed from backend -->
    <script>
        const config = {
            hasLabTests: @json($hasLabTests),
            hasMultipleDoctors: @json($hasMultipleDoctors),
            hasMultipleChambers: @json($hasMultipleChambers),
            chambersCount: @json(count($chambers)),
            doctorsCount: @json(count($doctors)),
            chamberIds: @json($chambers->pluck('id')->map(fn ($id) => (string) $id)->values()),
            doctorIds: @json($doctors->pluck('id')->map(fn ($id) => (string) $id)->values()),
            canBookConsultation: @json($canBookConsultation),
            canBookLab: @json($canBookLab),
            payAtClinic: @json(__('Pay at the clinic')),
            confirmLabel: @json(__('Confirm Booking')),
            bookingLabel: @json(__('Booking…')),
            phoneInvalid: @json(__('Please enter a valid Bangladeshi mobile number, for example 01712345678.')),
            localeTag: @json(app()->getLocale() === 'bn' ? 'bn-BD' : 'en-GB'),
        };

        const i18n = {
            nextAvailable: @json(__('Next available: :date')),
            dateMismatch: @json(__('This date is a :got. You need to pick a :expected.')),
            selectSchedule: @json(__('Select a Schedule')),
            selectLabWindow: @json(__('Select a Lab Collection Window')),
            noSchedulesTitle: @json(__('No schedules available')),
            noSchedulesBody: @json(__('There are no consultation sessions for this selection. Please go back and try another option, or contact the clinic.')),
            noLabTitle: @json(__('No lab slots available')),
            noLabBody: @json(__('There are no collection windows for this location. Please go back or contact the clinic.')),
            doctorChamber: @json(__('Doctor: :doctor | Chamber: :chamber')),
            seatsLeft: @json(__(':remaining of :cap left')),
            full: @json(__('Full')),
            closed: @json(__('Closed')),
            nextDay: @json(__('next :date')),
            checkingSeats: @json(__('Checking seats…')),
            labCollection: @json(__(':day collection')),
            sessionFallback: @json(__('Session')),
            mainLab: @json(__('Main Lab')),
            selectLabTest: @json(__('Please select at least one lab test.')),
            validationError: @json(__('Validation error. Please check your inputs.')),
            submitError: @json(__('An error occurred submitting the booking.')),
        };
        
        const sessionsData = @json($sessions);
        const labSlotsData = @json($labSlots);
        const dayLabels = @json(array_values(\App\Support\DayOfWeek::options()));

        let state = {
            type: null,
            chamberId: null,
            doctorId: null,
            bookableId: null,
            dayOfWeek: null,
            sessionName: null,
            startTime: null,
            endTime: null,
            doctorName: null,
            chamberName: null,
        };

        let flow = [];
        let availabilityCache = {};
        
        function rebuildFlow() {
            flow = [];
            
            if (config.hasLabTests && config.canBookConsultation && config.canBookLab) {
                if (!state.type) {
                    flow.push('step-type');
                }
            } else if (config.canBookLab && !config.canBookConsultation) {
                state.type = 'lab';
            } else if (!state.type) {
                state.type = 'session';
            }
            
            if (config.hasMultipleChambers && config.chambersCount > 1) {
                if (!state.chamberId || !config.chamberIds.includes(String(state.chamberId))) {
                    flow.push('step-chamber');
                }
            } else {
                @if(count($chambers) > 0)
                state.chamberId = state.chamberId || "{{ $chambers->first()->id }}";
                @endif
            }
            
            if (state.type !== 'lab' && config.hasMultipleDoctors && config.doctorsCount > 1) {
                if (!state.doctorId || !config.doctorIds.includes(String(state.doctorId))) {
                    flow.push('step-doctor');
                }
            } else if (state.type !== 'lab') {
                @if(count($doctors) > 0)
                state.doctorId = state.doctorId || "{{ $doctors->first()->id }}";
                @endif
            }
            
            flow.push('step-session');
            
            if (state.type === 'lab') {
                flow.push('step-lab-tests');
            }
            
            flow.push('step-identity');
            renderProgress();
        }

        let currentStepIndex = 0;

        function localToday() {
            const d = new Date();
            return new Date(d.getFullYear(), d.getMonth(), d.getDate());
        }

        function toLocalYmd(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        function nextDateForDow(dayOfWeek, fromDate = null) {
            const start = fromDate ? new Date(fromDate.getFullYear(), fromDate.getMonth(), fromDate.getDate()) : localToday();
            const next = new Date(start);
            while (next.getDay() !== dayOfWeek) {
                next.setDate(next.getDate() + 1);
            }
            return next;
        }

        function isValidBdPhone(value) {
            return /^(?:\+?88)?01[3-9]\d{8}$/.test(String(value).trim());
        }

        function formatTime(t) {
            return String(t || '').substring(0, 5);
        }

        function seatsLabel(info) {
            if (!info || info.missing) return { text: '', className: '' };
            if (info.blocked) return { text: i18n.closed, className: 'is-closed' };
            if (info.remaining <= 0) return { text: i18n.full, className: 'is-full' };
            return {
                text: i18n.seatsLeft.replace(':remaining', info.remaining).replace(':cap', info.cap),
                className: '',
            };
        }

        async function fetchAvailability(bookableType, ids, dateYmd) {
            const key = `${bookableType}:${dateYmd}:${ids.slice().sort().join(',')}`;
            if (availabilityCache[key]) return availabilityCache[key];

            const params = new URLSearchParams();
            params.set('bookable_type', bookableType);
            params.set('booking_date', dateYmd);
            ids.forEach(id => params.append('bookable_ids[]', id));

            const response = await fetch(`/api/bookings/availability?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('availability failed');
            const data = await response.json();
            availabilityCache[key] = data.items || {};
            return availabilityCache[key];
        }

        function invalidateAvailability() {
            availabilityCache = {};
        }

        function renderProgress() {
            const bar = document.getElementById('progressBar');
            bar.innerHTML = '';
            flow.forEach((step, idx) => {
                const dot = document.createElement('div');
                dot.className = 'progress-dot';
                if (idx < currentStepIndex) dot.classList.add('completed');
                if (idx === currentStepIndex) dot.classList.add('active');
                bar.appendChild(dot);
            });
        }

        function showStep() {
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            document.getElementById(flow[currentStepIndex]).classList.add('active');
            renderProgress();
            
            const currentStepId = flow[currentStepIndex];
            if (currentStepId === 'step-session') {
                renderSessions();
            } else if (currentStepId === 'step-identity') {
                setupDateConstraint();
            }
        }

        function setupDateConstraint() {
            const dateInput = document.getElementById('booking_date');
            const dateError = document.getElementById('date-error');
            const submitBtn = document.getElementById('submitBtn');
            const phoneError = document.getElementById('phone-error');
            
            const today = localToday();
            dateInput.min = toLocalYmd(today);
            const max = new Date(today);
            max.setDate(max.getDate() + 60);
            dateInput.max = toLocalYmd(max);
            
            const nextValid = nextDateForDow(state.dayOfWeek, today);
            const formatted = nextValid.toLocaleDateString(config.localeTag, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            document.getElementById('date-helper').innerText = i18n.nextAvailable.replace(':date', formatted);
            dateInput.value = toLocalYmd(nextValid);
            
            dateInput.onchange = () => {
                validateDate();
                refreshIdentityAvailability();
                updateReviewSummary();
            };
            document.getElementById('patient_phone').oninput = () => {
                phoneError.style.display = 'none';
            };
            dateError.style.display = 'none';
            phoneError.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.textContent = config.confirmLabel;
            updateReviewSummary();
            refreshIdentityAvailability();
        }

        function updateReviewSummary() {
            const summary = document.getElementById('reviewSummary');
            const dateInput = document.getElementById('booking_date');
            let dateLabel = dateInput.value || '';
            if (dateInput.value) {
                const d = new Date(dateInput.value + 'T00:00:00');
                dateLabel = d.toLocaleDateString(config.localeTag, { weekday: 'short', day: 'numeric', month: 'short' });
            }
            const title = state.type === 'lab'
                ? i18n.labCollection.replace(':day', dayLabels[state.dayOfWeek] || '')
                : (state.sessionName || i18n.sessionFallback);
            const parts = [
                state.doctorName,
                dateLabel,
                `${title} ${formatTime(state.startTime)}–${formatTime(state.endTime)}`,
                state.chamberName,
            ].filter(Boolean);
            summary.innerHTML = '';
            const strong = document.createElement('strong');
            strong.textContent = parts.join(' · ');
            summary.appendChild(strong);
        }

        async function refreshIdentityAvailability() {
            const seatsEl = document.getElementById('reviewSeats');
            const submitBtn = document.getElementById('submitBtn');
            const dateInput = document.getElementById('booking_date');
            if (!state.bookableId || !dateInput.value) return;

            seatsEl.textContent = i18n.checkingSeats;
            seatsEl.className = 'seats-line';

            try {
                const items = await fetchAvailability(state.type === 'lab' ? 'lab' : 'session', [state.bookableId], dateInput.value);
                const info = items[String(state.bookableId)];
                const label = seatsLabel(info);
                seatsEl.textContent = label.text || '—';
                seatsEl.className = 'seats-line ' + (label.className || '');

                const dateOk = validateDate(true);
                const open = info && info.available && !info.missing;
                submitBtn.disabled = !dateOk || !open;
            } catch (e) {
                seatsEl.textContent = '';
                submitBtn.disabled = !validateDate(true);
            }
        }

        function validateDate(silent = false) {
            const dateInput = document.getElementById('booking_date');
            const dateError = document.getElementById('date-error');
            const submitBtn = document.getElementById('submitBtn');
            
            if (!dateInput.value) {
                if (!silent) dateError.style.display = 'none';
                return false;
            }
            
            const selected = new Date(dateInput.value + 'T00:00:00');
            const selectedDay = selected.getDay();
            
            if (selectedDay !== state.dayOfWeek) {
                const expected = dayLabels[state.dayOfWeek];
                const got = dayLabels[selectedDay];
                dateError.innerText = i18n.dateMismatch.replace(':got', got).replace(':expected', expected);
                dateError.style.display = 'block';
                if (!silent) submitBtn.disabled = true;
                return false;
            }

            dateError.style.display = 'none';
            return true;
        }

        function nextStep() {
            if (currentStepIndex < flow.length - 1) {
                currentStepIndex++;
                showStep();
            }
        }

        function prevStep() {
            if (currentStepIndex > 0) {
                currentStepIndex--;
                if (flow[currentStepIndex] === 'step-type') {
                    state.type = null;
                }
                showStep();
            }
        }
        
        function selectType(type) {
            state.type = type;
            rebuildFlow();
            nextStep();
        }
        
        function selectChamber(id) {
            state.chamberId = id;
            document.querySelectorAll('#chamber-grid .selection-card').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            setTimeout(nextStep, 200);
        }
        
        function selectDoctor(id) {
            state.doctorId = id;
            document.querySelectorAll('#doctor-grid .selection-card').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            setTimeout(nextStep, 200);
        }
        
        function selectBookable(id, dayOfWeek, meta = {}) {
            state.bookableId = id;
            state.dayOfWeek = dayOfWeek;
            state.sessionName = meta.sessionName || null;
            state.startTime = meta.startTime || null;
            state.endTime = meta.endTime || null;
            state.doctorName = meta.doctorName || null;
            state.chamberName = meta.chamberName || null;
            document.querySelectorAll('#session-grid .selection-card').forEach(el => el.classList.remove('selected'));
            const card = document.querySelector(`#session-grid .selection-card[data-id="${id}"]`);
            if (card) card.classList.add('selected');
            setTimeout(nextStep, 200);
        }
        
        async function renderSessions() {
            const grid = document.getElementById('session-grid');
            const title = document.getElementById('session-title');
            grid.replaceChildren();
            
            if (state.type === 'session') {
                title.innerText = i18n.selectSchedule;
                const filtered = sessionsData.filter(s => 
                    (!state.chamberId || s.chamber_id == state.chamberId) && 
                    (!state.doctorId || s.doctor_id == state.doctorId)
                );
                
                if (filtered.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'text-muted';
                    empty.style.cssText = 'padding:1rem 0;line-height:1.5;';
                    const heading = document.createElement('p');
                    heading.style.cssText = 'margin:0 0 0.5rem;font-weight:600;color:#0f172a;';
                    heading.textContent = i18n.noSchedulesTitle;
                    const body = document.createElement('p');
                    body.style.margin = '0';
                    body.textContent = i18n.noSchedulesBody;
                    empty.append(heading, body);
                    grid.appendChild(empty);
                    return;
                }

                // Group by next occurrence date so we can batch availability
                const byDate = {};
                filtered.forEach(s => {
                    const ymd = toLocalYmd(nextDateForDow(s.day_of_week));
                    if (!byDate[ymd]) byDate[ymd] = [];
                    byDate[ymd].push(s);
                });

                let itemsById = {};
                try {
                    for (const [ymd, list] of Object.entries(byDate)) {
                        const chunk = await fetchAvailability('session', list.map(s => s.id), ymd);
                        itemsById = { ...itemsById, ...chunk };
                    }
                } catch (e) {
                    itemsById = {};
                }
                
                filtered.forEach(s => {
                    const tStart = formatTime(s.start_time);
                    const tEnd = formatTime(s.end_time);
                    const day = dayLabels[s.day_of_week] || '';
                    const nextYmd = toLocalYmd(nextDateForDow(s.day_of_week));
                    const info = itemsById[String(s.id)];
                    const seats = seatsLabel(info);
                    const disabled = info && (info.blocked || info.remaining <= 0 || info.missing);

                    const card = document.createElement('div');
                    card.className = 'selection-card' + (disabled ? ' is-disabled' : '');
                    card.dataset.id = String(s.id);
                    if (!disabled) {
                        card.addEventListener('click', () => selectBookable(String(s.id), s.day_of_week, {
                            sessionName: s.session_name,
                            startTime: s.start_time,
                            endTime: s.end_time,
                            doctorName: s.doctor?.name,
                            chamberName: s.chamber?.name,
                        }));
                    }

                    const heading = document.createElement('h4');
                    heading.textContent = s.session_name ?? '';

                    const meta = document.createElement('p');
                    meta.textContent = `${day} • ${tStart} - ${tEnd}`;

                    const detail = document.createElement('p');
                    detail.className = 'text-muted';
                    detail.style.cssText = 'font-size:0.8rem; margin-top:0.5rem;';
                    detail.textContent = i18n.doctorChamber
                        .replace(':doctor', s.doctor?.name ?? '')
                        .replace(':chamber', s.chamber?.name ?? '');

                    const seatsEl = document.createElement('div');
                    seatsEl.className = 'seats ' + (seats.className || '');
                    const nextLabel = nextDateForDow(s.day_of_week).toLocaleDateString(config.localeTag, { day: 'numeric', month: 'short' });
                    seatsEl.textContent = seats.text
                        ? `${seats.text} · ${i18n.nextDay.replace(':date', nextLabel)}`
                        : i18n.nextDay.replace(':date', nextLabel);

                    card.append(heading, meta, detail, seatsEl);
                    grid.appendChild(card);
                });
            } else {
                title.innerText = i18n.selectLabWindow;
                const filtered = labSlotsData.filter(s => 
                    (!state.chamberId || s.chamber_id == state.chamberId)
                );
                
                if (filtered.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'text-muted';
                    empty.style.cssText = 'padding:1rem 0;line-height:1.5;';
                    const heading = document.createElement('p');
                    heading.style.cssText = 'margin:0 0 0.5rem;font-weight:600;color:#0f172a;';
                    heading.textContent = i18n.noLabTitle;
                    const body = document.createElement('p');
                    body.style.margin = '0';
                    body.textContent = i18n.noLabBody;
                    empty.append(heading, body);
                    grid.appendChild(empty);
                    return;
                }

                const byDate = {};
                filtered.forEach(s => {
                    const ymd = toLocalYmd(nextDateForDow(s.day_of_week));
                    if (!byDate[ymd]) byDate[ymd] = [];
                    byDate[ymd].push(s);
                });
                let itemsById = {};
                try {
                    for (const [ymd, list] of Object.entries(byDate)) {
                        const chunk = await fetchAvailability('lab', list.map(s => s.id), ymd);
                        itemsById = { ...itemsById, ...chunk };
                    }
                } catch (e) {
                    itemsById = {};
                }
                
                filtered.forEach(s => {
                    const tStart = formatTime(s.start_time);
                    const tEnd = formatTime(s.end_time);
                    const day = dayLabels[s.day_of_week];
                    const chamberName = s.chamber ? s.chamber.name : i18n.mainLab;
                    const info = itemsById[String(s.id)];
                    const seats = seatsLabel(info);
                    const disabled = info && (info.blocked || info.remaining <= 0 || info.missing);

                    const card = document.createElement('div');
                    card.className = 'selection-card' + (disabled ? ' is-disabled' : '');
                    card.dataset.id = String(s.id);
                    if (!disabled) {
                        card.addEventListener('click', () => selectBookable(String(s.id), s.day_of_week, {
                            sessionName: null,
                            startTime: s.start_time,
                            endTime: s.end_time,
                            doctorName: null,
                            chamberName,
                        }));
                    }

                    const heading = document.createElement('h4');
                    heading.textContent = `${dayLabels[s.day_of_week] || ''}`;

                    const meta = document.createElement('p');
                    meta.textContent = `${tStart} - ${tEnd} • ${chamberName}`;

                    const seatsEl = document.createElement('div');
                    seatsEl.className = 'seats ' + (seats.className || '');
                    const nextLabel = nextDateForDow(s.day_of_week).toLocaleDateString(config.localeTag, { day: 'numeric', month: 'short' });
                    seatsEl.textContent = seats.text
                        ? `${seats.text} · ${i18n.nextDay.replace(':date', nextLabel)}`
                        : i18n.nextDay.replace(':date', nextLabel);

                    card.append(heading, meta, seatsEl);
                    grid.appendChild(card);
                });
            }
        }
        
        function updateTotal() {
            let total = 0;
            document.querySelectorAll('input[name="lab_tests[]"]:checked').forEach(cb => {
                total += parseFloat(cb.dataset.price);
            });
            document.getElementById('labTotalAmount').innerText = '৳' + total.toFixed(2);
            const err = document.getElementById('labTestError');
            if (err) err.style.display = 'none';
        }

        function continueLabTests() {
            const checked = document.querySelectorAll('input[name="lab_tests[]"]:checked');
            const err = document.getElementById('labTestError');
            if (checked.length === 0) {
                if (err) {
                    err.textContent = i18n.selectLabTest;
                    err.style.display = 'block';
                }
                return;
            }
            if (err) err.style.display = 'none';
            nextStep();
        }

        document.getElementById('bookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msgEl = document.getElementById('message');
            const submitBtn = document.getElementById('submitBtn');
            const phoneError = document.getElementById('phone-error');
            const phone = document.getElementById('patient_phone').value.trim();

            msgEl.className = 'alert';
            msgEl.style.display = 'none';
            msgEl.innerText = '';
            phoneError.style.display = 'none';

            if (!validateDate()) return;

            if (!isValidBdPhone(phone)) {
                phoneError.innerText = config.phoneInvalid;
                phoneError.style.display = 'block';
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = config.bookingLabel;
            
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('bookable_type', state.type);
            formData.append('bookable_id', state.bookableId);
            formData.append('booking_date', document.getElementById('booking_date').value);
            formData.append('patient_name', document.getElementById('patient_name').value);
            formData.append('patient_phone', phone);
            
            if (state.type === 'lab') {
                document.querySelectorAll('input[name="lab_tests[]"]:checked').forEach(cb => {
                    formData.append('lab_tests[]', cb.value);
                });
            }
            
            try {
                const response = await fetch('/api/bookings', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    document.getElementById('bookingForm').classList.add('hidden');
                    const successView = document.getElementById('successView');
                    successView.classList.remove('hidden');
                    document.getElementById('serialBadge').innerText = data.booking.serial_number;
                    const openBtn = document.getElementById('openTicketNow');
                    openBtn.href = data.booking.ticket_url;
                    
                    setTimeout(() => {
                        window.location.href = data.booking.ticket_url;
                    }, 1800);
                } else {
                    let message = data.message || i18n.validationError;
                    if (data.errors) {
                        const first = Object.values(data.errors).flat()[0];
                        if (first) message = first;
                    }
                    msgEl.innerText = message;
                    msgEl.className = 'alert alert-error';
                    msgEl.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = config.confirmLabel;

                    // Rahim lost the last seat — refresh seats and mark Full
                    if (data.code === 'capacity' || data.code === 'blocked' || data.code === 'unavailable') {
                        invalidateAvailability();
                        await refreshIdentityAvailability();
                    }
                }
            } catch (err) {
                msgEl.innerText = i18n.submitError;
                msgEl.className = 'alert alert-error';
                msgEl.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = config.confirmLabel;
            }
        });

        // Deep link: ?doctor=ID skips doctor pick; ?test=ID opens lab and pre-checks that test
        const params = new URLSearchParams(window.location.search);
        const doctorParam = params.get('doctor') || params.get('doctor_id');
        const testParam = params.get('test');
        if (doctorParam && config.doctorIds.includes(String(doctorParam))) {
            state.type = 'session';
            state.doctorId = String(doctorParam);
            const doctorSessions = sessionsData.filter(s => String(s.doctor_id) === String(doctorParam));
            const chamberIds = [...new Set(doctorSessions.map(s => String(s.chamber_id)))];
            if (chamberIds.length === 1) {
                state.chamberId = chamberIds[0];
            }
        }
        if (testParam) {
            state.type = 'lab';
            state.preselectedTestId = String(testParam);
        }

        rebuildFlow();
        showStep();

        if (state.preselectedTestId) {
            const cb = document.querySelector(`input[name="lab_tests[]"][value="${state.preselectedTestId}"]`);
            if (cb) {
                cb.checked = true;
                updateTotal();
            }
        }
    </script>
    @endif
