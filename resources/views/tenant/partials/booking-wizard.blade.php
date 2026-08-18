{{-- Shared booking wizard markup + JS. Shells: tenant.book / tenant.solo.book --}}
@php
    /*
     * Only the fields the wizard's JS actually reads. Serialising the whole
     * Eloquent models put tenant_id, slot_cap, timestamps and full nested
     * doctor/chamber records into the page source for every patient — internal
     * capacity data, and dead weight on the slowest connection we serve.
     */
    $sessionsPayload = ($sessions ?? collect())->map(fn ($s) => [
        'id' => (string) $s->id,
        'chamber_id' => (string) $s->chamber_id,
        'doctor_id' => (string) $s->doctor_id,
        'day_of_week' => (int) $s->day_of_week,
        'session_name' => $s->session_name,
        'start_time' => $s->start_time,
        'end_time' => $s->end_time,
        'doctor' => ['name' => $s->doctor?->name],
        'chamber' => ['name' => $s->chamber?->name],
    ])->values();

    $labSlotsPayload = ($labSlots ?? collect())->map(fn ($s) => [
        'id' => (string) $s->id,
        'chamber_id' => (string) $s->chamber_id,
        'day_of_week' => (int) $s->day_of_week,
        'start_time' => $s->start_time,
        'end_time' => $s->end_time,
        'chamber' => $s->chamber ? ['name' => $s->chamber->name] : null,
    ])->values();
@endphp
            @if(! $bookingAvailable)
                <div class="booking-header">
                    <h2>{{ __('Booking unavailable') }}</h2>
                    <p class="text-muted" style="margin-top: 1rem; line-height: 1.6;">
                        @if($bookingClosedForBilling ?? false)
                            {{ __('Online booking is temporarily unavailable. Please call the clinic to book your appointment.') }}
                        @else
                            {{ __('This clinic has not published schedules yet. Please contact the clinic and try again later.') }}
                        @endif
                    </p>
                    @if(filled($tenant->contact_phone))
                        <p style="margin-top: 1.5rem;">
                            <a href="tel:{{ $tenant->contact_phone }}" class="btn" style="display: inline-block;">
                                {{ __('Call') }} {{ $tenant->contact_phone }}
                            </a>
                        </p>
                    @endif
                    <p style="margin-top: 1.5rem;">
                        <a href="{{ tenant_web_url('/') }}" class="btn btn-back" style="display: inline-block;">{{ __('Back to home') }}</a>
                    </p>
                </div>
            @else
            <div class="booking-header">
                <h2>{{ __('Book Your Appointment') }}</h2>
                <div class="progress-bar" id="progressBar"></div>
                <p class="step-label" id="stepLabel" aria-live="polite"></p>
            </div>
            
            <div id="message" class="alert" role="alert" aria-live="polite"></div>

            <form id="bookingForm">
                @csrf
                
                <!-- STEP 1: Type Selection -->
                <div class="step" id="step-type" data-step-name="type">
                    <h3>{{ __('What would you like to book?') }}</h3>
                    <div class="selection-grid">
                        @if($canBookConsultation)
                        <button type="button" class="selection-card" onclick="selectType('session', this)">
                            <span class="sc-title">{{ __('Doctor Consultation') }}</span>
                            <span class="sc-sub">{{ __('Book a visit with one of our specialists.') }}</span>
                        </button>
                        @endif
                        @if($hasLabTests && $canBookLab)
                        <button type="button" class="selection-card" onclick="selectType('lab', this)">
                            <span class="sc-title">{{ __('Lab Tests') }}</span>
                            <span class="sc-sub">{{ __('Book pathology and imaging tests.') }}</span>
                        </button>
                        @endif
                    </div>
                </div>

                <!-- STEP 2: Chamber Selection -->
                <div class="step" id="step-chamber" data-step-name="chamber">
                    <h3>{{ __('Select a Location') }}</h3>
                    <div class="selection-grid" id="chamber-grid">
                        @foreach($chambers as $chamber)
                        <button type="button" class="selection-card" onclick="selectChamber('{{ $chamber->id }}', this)">
                            <span class="sc-title">{{ $chamber->name }}</span>
                            <span class="sc-sub">{{ $chamber->address }}</span>
                        </button>
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
                        <button type="button" class="selection-card" onclick="selectDoctor('{{ $doctor->id }}', this)">
                            <span class="sc-title">{{ $doctor->name }}</span>
                            <span class="sc-sub">{{ $doctor->specialty }}</span>
                        </button>
                        @endforeach
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-back" onclick="prevStep()">{{ __('Back') }}</button>
                    </div>
                </div>

                <!-- STEP: When can you come? (open dates only) -->
                <div class="step" id="step-when" data-step-name="when">
                    <h3 id="when-title">{{ __('When can you come?') }}</h3>
                    <p class="text-muted" id="when-subtitle" style="margin-bottom:1rem;line-height:1.5;">{{ __('Only dates with seats left are shown, soonest first.') }}</p>
                    <div class="selection-grid list-view" id="when-grid">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- STEP 5: Lab Tests Selection -->
                <div class="step" id="step-lab-tests" data-step-name="lab-tests">
                    <h3>{{ __('Select Lab Tests') }}</h3>
                    <p class="text-muted" style="margin-bottom:1rem;">{{ __('You can select multiple tests.') }}</p>
                    <div class="selection-grid list-view">
                        @foreach($labTests as $test)
                        <label class="selection-card" style="display:block; padding-left:3rem; position:relative;">
                            <input type="checkbox" name="lab_tests[]" value="{{ $test->id }}" data-price="{{ $test->price }}" onchange="updateTotal()" style="position:absolute; left:1rem; top:1.5rem; width:1.35rem; height:1.35rem; accent-color: var(--color-primary);">
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

                <!-- Identity -->
                <div class="step" id="step-identity" data-step-name="identity">
                    <div class="booking-review" id="bookingReview" aria-live="polite">
                        <div id="reviewSummary"></div>
                    </div>

                    <h3>{{ __('Your details') }}</h3>
                    <input type="hidden" name="booking_date" id="booking_date" required>

                    <div class="form-group field-float">
                        <input type="tel" name="patient_phone" id="patient_phone" class="form-control"
                               inputmode="numeric" autocomplete="tel" maxlength="14"
                               placeholder=" " required>
                        <label class="field-float-label" for="patient_phone">{{ __('Phone') }}</label>
                        <span class="field-error" id="phone-error" role="alert" aria-live="polite" style="display:none"></span>
                    </div>

                    <div class="form-group field-float">
                        <input type="text" name="patient_name" id="patient_name" class="form-control" autocomplete="name" placeholder=" " required>
                        <label class="field-float-label" for="patient_name">{{ __('Name') }}</label>
                        <small class="text-muted" id="patientNameHint" style="display:none;margin-top:0.4rem"></small>
                    </div>

                    <div class="form-group field-float">
                        {{-- Birth year is the stable number. Age on the pad is
                             this calendar year minus that, so 1984 does not
                             become 1985 next year. --}}
                        <input type="number" name="year_of_birth" id="patient_year_of_birth" class="form-control"
                               inputmode="numeric" autocomplete="bday-year"
                               min="{{ \App\Support\YearOfBirth::minYear() }}"
                               max="{{ \App\Support\YearOfBirth::maxYear() }}"
                               step="1"
                               placeholder=" ">
                        <label class="field-float-label" for="patient_year_of_birth">{{ __('Year of birth (optional)') }}</label>
                        <small class="text-muted" style="display:block;margin-top:0.4rem">{{ __('Example: 1984. Helps the doctor see your past visits from other chambers.') }}</small>
                    </div>

                    <div class="form-group field-float">
                        <input type="text" name="nid" id="patient_nid" class="form-control"
                               inputmode="numeric" autocomplete="off" maxlength="17"
                               placeholder=" ">
                        <label class="field-float-label" for="patient_nid">{{ __('NID (optional)') }}</label>
                        <span class="field-error" id="nid-error" role="alert" aria-live="polite" style="display:none"></span>
                    </div>

                    <div class="form-group">
                        <label class="booking-check" style="display:flex;align-items:center;gap:0.65rem;cursor:pointer;">
                            <input type="checkbox" name="different_whatsapp" id="different_whatsapp" value="1" style="width:1.15rem;height:1.15rem;accent-color:var(--color-primary);">
                            <span style="font-weight:600;">{{ __('Different WhatsApp') }}</span>
                        </label>
                    </div>

                    <div class="form-group field-float hidden" id="whatsappPhoneGroup">
                        <input type="tel" name="whatsapp_phone" id="whatsapp_phone" class="form-control"
                               inputmode="numeric" autocomplete="tel" maxlength="14"
                               placeholder=" ">
                        <label class="field-float-label" for="whatsapp_phone">{{ __('WhatsApp') }}</label>
                        <span class="field-error" id="whatsapp-error" role="alert" aria-live="polite" style="display:none"></span>
                    </div>

                    <div class="form-group hidden" id="patientPickerGroup" aria-live="polite">
                        <p class="form-label" style="margin-bottom:0.75rem;font-weight:600;">{{ __('Who for?') }}</p>
                        <div id="patientPickerOptions" class="patient-picker-options"></div>
                        <input type="hidden" name="patient_id" id="patient_id" value="">
                    </div>

                    <div class="form-group">
                        <label class="booking-check" style="display:flex;align-items:center;gap:0.65rem;cursor:pointer;">
                            <input type="checkbox" name="share_clinical_history" id="share_clinical_history" value="1" checked style="width:1.15rem;height:1.15rem;accent-color:var(--color-primary);">
                            <span style="font-weight:600;">{{ __('Share with other ChamberQ doctors') }}</span>
                        </label>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-back" id="changeDateBtn">{{ __('Change booking date') }}</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">{{ __('Confirm Booking') }}</button>
                    </div>
                </div>
            </form>
            
            <div id="successView" class="hidden" style="text-align: center;">
                <h3>{{ __('Booking Confirmed!') }}</h3>
                <p>{{ __('Your serial number is:') }}</p>
                <div style="font-size: 3rem; font-weight:700; color: var(--color-primary); margin: 1rem 0;" id="serialBadge"></div>
                <p id="comeAroundHint" class="hidden" style="font-size:1.1rem;font-weight:600;color:var(--color-primary);margin:0 0 0.75rem;"></p>
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
            confirmLabel: @json(__('Confirm Booking')),
            continueLabel: @json(__('Continue')),
            bookingLabel: @json(__('Booking…')),
            basePath: @json(rtrim(tenant_web_url(''), '/')),
            prefill: @json($prefill ?? []),
            phoneInvalid: @json(__('Please enter a valid Bangladeshi mobile number, for example 01712345678.')),
            whatsappInvalid: @json(__('Please enter a valid Bangladeshi WhatsApp number, for example 01712345678.')),
            localeTag: @json(app()->getLocale() === 'bn' ? 'bn-BD' : 'en-GB'),
            contactPhone: @json(filled($tenant->contact_phone ?? null) ? $tenant->contact_phone : null),
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
            nextDay: @json(__('next :date')),
            checkingSeats: @json(__('Checking seats…')),
            labCollection: @json(__(':day collection')),
            sessionFallback: @json(__('Session')),
            mainLab: @json(__('Main Lab')),
            selectLabTest: @json(__('Please select at least one lab test.')),
            validationError: @json(__('Validation error. Please check your inputs.')),
            submitError: @json(__('An error occurred submitting the booking.')),
            stepCounter: @json(__('Step :current of :total')),
            stepType: @json(__('Choose booking type')),
            stepChamber: @json(__('Pick location')),
            stepDoctor: @json(__('Pick doctor')),
            stepWhen: @json(__('Pick a date')),
            stepLabWindow: @json(__('Pick collection time')),
            stepLabTests: @json(__('Select tests')),
            stepIdentity: @json(__('Your details')),
            whoIsThisFor: @json(__('Who is this appointment for?')),
            someoneNew: @json(__('Someone new')),
            usingRecordOnFile: @json(__('We already have this patient on file — no need to type the name again.')),
            whenCanYouCome: @json(__('When can you come?')),
            whenSubtitle: @json(__('Only dates with seats left are shown, soonest first.')),
            earliestAvailable: @json(__('Earliest available')),
            seeMoreDates: @json(__('See more dates')),
            showFewerDates: @json(__('Show fewer dates')),
            noOpenDatesTitle: @json(__('No seats available soon')),
            noOpenDatesBody: @json(__('Every date in the next two months is full or closed. Please call the clinic or try again later.')),
            callClinic: @json(__('Call the clinic')),
        };
        
        let selectedPatientId = null;
        let patientLookupTimer = null;
        const sessionsData = @json($sessionsPayload);
        const labSlotsData = @json($labSlotsPayload);
        const dayLabels = @json(array_values(\App\Support\DayOfWeek::options()));

        let state = {
            type: null,
            // Set only by ?doctor= / ?test= deep links, which pick the type for the
            // patient and therefore skip the type step entirely.
            typeLocked: false,
            chamberId: null,
            doctorId: null,
            bookableId: null,
            dayOfWeek: null,
            sessionName: null,
            startTime: null,
            endTime: null,
            doctorName: null,
            chamberName: null,
            prefilledDate: null,
            prefilledPhone: null,
            prefilledName: null,
        };

        let flow = [];
        let availabilityCache = {};
        let openDatesExpanded = false;
        const initialVisibleDates = 5;
        
        function rebuildFlow() {
            flow = [];
            
            if (config.hasLabTests && config.canBookConsultation && config.canBookLab) {
                // Keep the type step in the flow after a choice is made. Dropping it
                // shifted every later step down one index, so the step right after it
                // (Pick location) was skipped, and Back could never return here.
                if (!state.typeLocked) {
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
            
            // Keep the when step in the flow after a date is picked. Dropping it
            // (like we once did with the type step) shifted indices so nextStep
            // never reached identity — cards looked dead and there was no Continue.
            flow.push('step-when');
            
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

        // Same rule the server enforces: a session/window whose end_time has
        // already passed today is not offered as "today" — default to next week
        // instead of defaulting patients onto a slot that already finished.
        function nextAvailableDate(dayOfWeek, endTime, fromDate = null) {
            const next = nextDateForDow(dayOfWeek, fromDate);
            if (!endTime || toLocalYmd(next) !== toLocalYmd(localToday())) {
                return next;
            }
            const [h, m] = String(endTime).split(':').map(Number);
            const end = new Date();
            end.setHours(h || 0, m || 0, 0, 0);
            if (new Date() < end) {
                return next;
            }
            const tomorrow = new Date(next.getFullYear(), next.getMonth(), next.getDate() + 1);
            return nextDateForDow(dayOfWeek, tomorrow);
        }

        function isValidBdPhone(value) {
            return /^(?:\+?88)?01[3-9]\d{8}$/.test(String(value).trim());
        }

        function formatTime(t) {
            return String(t || '').substring(0, 5);
        }

        async function fetchAvailability(bookableType, ids, dateYmd) {
            const key = `${bookableType}:${dateYmd}:${ids.slice().sort().join(',')}`;
            if (availabilityCache[key]) return availabilityCache[key];

            const params = new URLSearchParams();
            params.set('bookable_type', bookableType);
            params.set('booking_date', dateYmd);
            ids.forEach(id => params.append('bookable_ids[]', id));

            const response = await fetch(`${config.basePath}/api/bookings/availability?${params.toString()}`, {
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

        function stepTitle(stepId) {
            switch (stepId) {
                case 'step-type': return i18n.stepType;
                case 'step-chamber': return i18n.stepChamber;
                case 'step-doctor': return i18n.stepDoctor;
                case 'step-when': return state.type === 'lab' ? i18n.stepLabWindow : i18n.stepWhen;
                case 'step-lab-tests': return i18n.stepLabTests;
                case 'step-identity': return i18n.stepIdentity;
                default: return '';
            }
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

            // Plain-language position ("Step 2 of 5 — Pick location") reassures
            // patients that the flow is short; dots alone do not.
            const label = document.getElementById('stepLabel');
            if (label) {
                const counter = i18n.stepCounter
                    .replace(':current', currentStepIndex + 1)
                    .replace(':total', flow.length);
                const title = stepTitle(flow[currentStepIndex]);
                label.textContent = title ? `${counter} — ${title}` : counter;
            }
        }

        function showStep() {
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            document.getElementById(flow[currentStepIndex]).classList.add('active');
            renderProgress();
            
            const currentStepId = flow[currentStepIndex];
            if (currentStepId === 'step-when') {
                renderOpenDates();
            } else if (currentStepId === 'step-identity') {
                setupDateConstraint();
            }
        }

        function goToWhenStep() {
            state.bookableId = null;
            state.prefilledDate = null;
            openDatesExpanded = false;
            rebuildFlow();
            const whenIdx = flow.indexOf('step-when');
            if (whenIdx >= 0) {
                currentStepIndex = whenIdx;
            }
            showStep();
        }

        function setupDateConstraint() {
            const dateInput = document.getElementById('booking_date');
            const submitBtn = document.getElementById('submitBtn');
            const phoneError = document.getElementById('phone-error');

            if (!state.prefilledDate) {
                goToWhenStep();
                return;
            }

            dateInput.value = state.prefilledDate;

            const changeBtn = document.getElementById('changeDateBtn');
            if (changeBtn && !changeBtn.dataset.bound) {
                changeBtn.dataset.bound = '1';
                changeBtn.addEventListener('click', goToWhenStep);
            }

            const phoneInput = document.getElementById('patient_phone');
            phoneInput.oninput = () => {
                phoneError.style.display = 'none';
                stripPhoneSeparators(phoneInput);
                updateConfirmEnabled();
                schedulePatientLookup();
            };
            phoneInput.onblur = () => {
                validatePhoneField(phoneInput, phoneError);
                schedulePatientLookup(true);
                updateConfirmEnabled();
            };

            const nameInput = document.getElementById('patient_name');
            nameInput.oninput = () => updateConfirmEnabled();

            const differentWa = document.getElementById('different_whatsapp');
            const waGroup = document.getElementById('whatsappPhoneGroup');
            const waInput = document.getElementById('whatsapp_phone');
            const waError = document.getElementById('whatsapp-error');
            if (differentWa && !differentWa.dataset.bound) {
                differentWa.dataset.bound = '1';
                differentWa.addEventListener('change', () => {
                    if (differentWa.checked) {
                        waGroup.classList.remove('hidden');
                    } else {
                        waGroup.classList.add('hidden');
                        waInput.value = '';
                        waError.style.display = 'none';
                    }
                    updateConfirmEnabled();
                });
            }
            if (waInput && !waInput.dataset.bound) {
                waInput.dataset.bound = '1';
                waInput.oninput = () => {
                    waError.style.display = 'none';
                    stripPhoneSeparators(waInput);
                    updateConfirmEnabled();
                };
                waInput.onblur = () => {
                    if (document.getElementById('different_whatsapp')?.checked) {
                        validatePhoneField(waInput, waError, config.whatsappInvalid);
                    }
                    updateConfirmEnabled();
                };
            }

            phoneError.style.display = 'none';
            if (state.prefilledPhone) {
                phoneInput.value = state.prefilledPhone;
                if (isValidBdPhone(state.prefilledPhone)) schedulePatientLookup(true);
            }
            if (state.prefilledName) {
                nameInput.value = state.prefilledName;
            }
            submitBtn.textContent = config.confirmLabel;
            updateReviewSummary();
            refreshIdentityAvailability();
            updateConfirmEnabled();
        }

        function stripPhoneSeparators(input) {
            const before = input.value;
            const cleaned = before.replace(/[^\d+]/g, '');
            if (cleaned === before) {
                return;
            }
            const caret = input.selectionStart ?? before.length;
            const head = before.slice(0, caret);
            const removed = head.length - head.replace(/[^\d+]/g, '').length;
            input.value = cleaned;
            const pos = Math.max(0, caret - removed);
            input.setSelectionRange(pos, pos);
        }

        function validatePhoneField(input, errorEl, message = null) {
            const value = input.value.trim();
            if (!value) {
                errorEl.style.display = 'none';
                return false;
            }
            if (!isValidBdPhone(value)) {
                errorEl.innerText = message || config.phoneInvalid;
                errorEl.style.display = 'block';
                return false;
            }
            errorEl.style.display = 'none';
            return true;
        }

        function identityFieldsComplete() {
            const dateOk = Boolean(state.prefilledDate && document.getElementById('booking_date').value);
            const phoneOk = isValidBdPhone(document.getElementById('patient_phone').value.trim());
            const patientId = document.getElementById('patient_id').value;
            const nameOk = Boolean(patientId) || document.getElementById('patient_name').value.trim().length > 0;
            const differentWa = document.getElementById('different_whatsapp')?.checked;
            const waOk = !differentWa || isValidBdPhone(document.getElementById('whatsapp_phone').value.trim());
            return dateOk && phoneOk && nameOk && waOk;
        }

        function updateConfirmEnabled() {
            const submitBtn = document.getElementById('submitBtn');
            if (!submitBtn) return;
            // Also respect live seat availability (refreshIdentityAvailability may disable).
            const seatsOpen = !submitBtn.dataset.seatsClosed;
            submitBtn.disabled = !identityFieldsComplete() || !seatsOpen;
        }

        function schedulePatientLookup(immediate = false) {
            clearTimeout(patientLookupTimer);
            patientLookupTimer = setTimeout(() => lookupPatientsForPhone(), immediate ? 0 : 400);
        }

        async function lookupPatientsForPhone() {
            const phoneInput = document.getElementById('patient_phone');
            const pickerGroup = document.getElementById('patientPickerGroup');
            const pickerOptions = document.getElementById('patientPickerOptions');
            const phone = phoneInput.value.trim();

            if (!isValidBdPhone(phone)) {
                pickerGroup.classList.add('hidden');
                selectPatient(null, '', null);
                return;
            }

            try {
                const response = await fetch(`${config.basePath}/api/patients/by-phone?phone=${encodeURIComponent(phone)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();

                if (!response.ok || !data.patients || data.patients.length === 0) {
                    pickerGroup.classList.add('hidden');
                    selectPatient(null, '', null);
                    return;
                }

                pickerOptions.innerHTML = '';
                // `label` is masked initials ("F. R., 34") — the endpoint is
                // public, so it never sends the real name. Picking someone
                // sends only their id; the server reads the name off the record.
                data.patients.forEach((patient) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'patient-picker-btn';
                    btn.textContent = patient.label;
                    btn.dataset.patientId = patient.id;
                    btn.onclick = () => selectPatient(patient.id, patient.label, btn);
                    pickerOptions.appendChild(btn);
                });

                const newBtn = document.createElement('button');
                newBtn.type = 'button';
                newBtn.className = 'patient-picker-btn patient-picker-btn-new';
                newBtn.textContent = i18n.someoneNew;
                newBtn.onclick = () => selectPatient(null, '', newBtn);
                pickerOptions.appendChild(newBtn);

                pickerGroup.classList.remove('hidden');
            } catch (e) {
                pickerGroup.classList.add('hidden');
            }
        }

        function selectPatient(patientId, patientLabel, clickedBtn) {
            selectedPatientId = patientId;
            document.getElementById('patient_id').value = patientId || '';
            document.querySelectorAll('.patient-picker-btn').forEach((btn) => btn.classList.remove('selected'));
            if (clickedBtn) clickedBtn.classList.add('selected');

            const nameInput = document.getElementById('patient_name');
            const nameHint = document.getElementById('patientNameHint');

            if (patientId) {
                // We only know this person's initials, so the name field is
                // stood down rather than filled with a mask. Disabled inputs
                // are not submitted, and the server takes the name from the id.
                nameInput.value = patientLabel || ' ';
                nameInput.disabled = true;
                nameInput.required = false;
                nameInput.placeholder = ' ';
                nameHint.textContent = i18n.usingRecordOnFile;
                nameHint.style.display = 'block';
                updateConfirmEnabled();
                return;
            }

            nameInput.disabled = false;
            nameInput.required = true;
            nameInput.value = '';
            nameInput.placeholder = ' ';
            nameHint.style.display = 'none';
            updateConfirmEnabled();
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
            const submitBtn = document.getElementById('submitBtn');
            const dateInput = document.getElementById('booking_date');
            if (!state.bookableId || !dateInput.value) return;

            try {
                const items = await fetchAvailability(state.type === 'lab' ? 'lab' : 'session', [state.bookableId], dateInput.value);
                const info = items[String(state.bookableId)];
                const open = info && info.available && !info.missing;
                submitBtn.dataset.seatsClosed = open ? '' : '1';
                updateConfirmEnabled();
            } catch (e) {
                submitBtn.dataset.seatsClosed = '';
                updateConfirmEnabled();
            }
        }

        function validateDate() {
            return Boolean(state.prefilledDate && document.getElementById('booking_date').value);
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
                // Returning to the date list should offer every open date again,
                // not only the session they just picked.
                if (flow[currentStepIndex] === 'step-when') {
                    state.bookableId = null;
                    state.prefilledDate = null;
                    openDatesExpanded = false;
                }
                showStep();
            }
        }
        
        // `el` is passed from the markup (`this`) rather than read off the global
        // `event`, so the selected state is reliable outside Chrome.
        function markSelected(gridSelector, el) {
            document.querySelectorAll(`${gridSelector} .selection-card`).forEach(card => card.classList.remove('selected'));
            if (el) el.classList.add('selected');
        }

        function selectType(type, el) {
            state.type = type;
            markSelected('#step-type', el);
            rebuildFlow();
            setTimeout(nextStep, 200);
        }


        function selectChamber(id, el) {
            state.chamberId = id;
            markSelected('#chamber-grid', el);
            setTimeout(nextStep, 200);
        }

        function selectDoctor(id, el) {
            state.doctorId = id;
            markSelected('#doctor-grid', el);
            setTimeout(nextStep, 200);
        }
        
        function selectOpenDate(option, el) {
            state.bookableId = String(option.bookable_id);
            state.dayOfWeek = option.day_of_week;
            state.prefilledDate = option.date;
            state.sessionName = option.session_name || null;
            state.startTime = option.start_time || null;
            state.endTime = option.end_time || null;
            state.doctorName = option.doctor?.name || null;
            state.chamberName = option.chamber?.name || null;
            markSelected('#when-grid', el);
            setTimeout(nextStep, 200);
        }

        async function fetchOpenDates(bookableType, ids) {
            const params = new URLSearchParams();
            params.set('bookable_type', bookableType);
            ids.forEach(id => params.append('bookable_ids[]', id));

            const response = await fetch(`${config.basePath}/api/bookings/open-dates?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('open-dates failed');
            const data = await response.json();
            return data.options || [];
        }

        function formatOpenDateTitle(ymd) {
            const d = new Date(ymd + 'T00:00:00');
            return d.toLocaleDateString(config.localeTag, { weekday: 'long', day: 'numeric', month: 'short' });
        }

        function buildWhenCard(option, { highlight = false } = {}) {
            const card = document.createElement('button');
            card.type = 'button';
            // Earliest gets a label badge only — not `.selected`, which made every
            // other card look untappable until a real click marks one.
            card.className = 'selection-card';
            card.addEventListener('click', () => selectOpenDate(option, card));

            const timeRange = `${formatTime(option.start_time)}–${formatTime(option.end_time)}`;

            if (highlight) {
                const badge = document.createElement('span');
                badge.className = 'sc-sub';
                badge.style.cssText = 'display:block;font-weight:600;margin-bottom:0.35rem;';
                badge.textContent = i18n.earliestAvailable;
                card.appendChild(badge);
            }

            const heading = document.createElement('span');
            heading.className = 'sc-title';
            heading.textContent = formatOpenDateTitle(option.date);

            const meta = document.createElement('span');
            meta.className = 'sc-sub';
            const detailParts = [];
            if (state.type !== 'lab' && option.session_name) {
                detailParts.push(option.session_name);
            }
            detailParts.push(timeRange);
            if (option.doctor?.name) detailParts.push(option.doctor.name);
            if (option.chamber?.name) detailParts.push(option.chamber.name);
            meta.textContent = detailParts.join(' · ');

            card.append(heading, meta);

            return card;
        }

        async function renderOpenDates() {
            const grid = document.getElementById('when-grid');
            const title = document.getElementById('when-title');
            const subtitle = document.getElementById('when-subtitle');
            grid.replaceChildren();
            title.textContent = i18n.whenCanYouCome;
            subtitle.textContent = i18n.whenSubtitle;

            const bookableType = state.type === 'lab' ? 'lab' : 'session';
            const sourceData = state.type === 'lab' ? labSlotsData : sessionsData;
            // Deep-link `?session=` locks the bookable; a normal browse must
            // still list every open date for the chosen chamber/doctor.
            const lockToSession = Boolean(state.typeLocked && state.bookableId && !state.prefilledDate);
            const filtered = sourceData.filter(s => {
                if (lockToSession && String(s.id) !== String(state.bookableId)) return false;
                if (state.chamberId && s.chamber_id != state.chamberId) return false;
                if (state.type !== 'lab' && state.doctorId && s.doctor_id != state.doctorId) return false;
                return true;
            });

            if (filtered.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'text-muted';
                empty.style.cssText = 'padding:1rem 0;line-height:1.5;';
                const heading = document.createElement('p');
                heading.style.cssText = 'margin:0 0 0.5rem;font-weight:600;color:#0f172a;';
                heading.textContent = state.type === 'lab' ? i18n.noLabTitle : i18n.noSchedulesTitle;
                const body = document.createElement('p');
                body.style.margin = '0';
                body.textContent = state.type === 'lab' ? i18n.noLabBody : i18n.noSchedulesBody;
                empty.append(heading, body);
                grid.appendChild(empty);
                return;
            }

            grid.appendChild(document.createTextNode(i18n.checkingSeats));

            let options = [];
            try {
                options = await fetchOpenDates(bookableType, filtered.map(s => s.id));
            } catch (e) {
                options = [];
            }

            grid.replaceChildren();

            if (options.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'text-muted';
                empty.style.cssText = 'padding:1rem 0;line-height:1.5;';
                const heading = document.createElement('p');
                heading.style.cssText = 'margin:0 0 0.5rem;font-weight:600;color:#0f172a;';
                heading.textContent = i18n.noOpenDatesTitle;
                const body = document.createElement('p');
                body.style.margin = '0';
                body.textContent = i18n.noOpenDatesBody;
                empty.append(heading, body);
                if (config.contactPhone) {
                    const call = document.createElement('a');
                    call.href = `tel:${config.contactPhone}`;
                    call.className = 'btn';
                    call.style.cssText = 'display:inline-block;margin-top:1rem;';
                    call.textContent = `${i18n.callClinic}: ${config.contactPhone}`;
                    empty.appendChild(call);
                }
                grid.appendChild(empty);
                return;
            }

            const visibleCount = openDatesExpanded ? options.length : Math.min(initialVisibleDates, options.length);
            options.slice(0, visibleCount).forEach((option, idx) => {
                const card = buildWhenCard(option, { highlight: idx === 0 });
                if (
                    state.prefilledDate
                    && String(option.bookable_id) === String(state.bookableId)
                    && option.date === state.prefilledDate
                ) {
                    card.classList.add('selected');
                }
                grid.appendChild(card);
            });

            if (options.length > initialVisibleDates) {
                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'btn btn-back';
                toggle.style.cssText = 'margin-top:0.75rem;width:100%;';
                toggle.textContent = openDatesExpanded ? i18n.showFewerDates : i18n.seeMoreDates;
                toggle.addEventListener('click', () => {
                    openDatesExpanded = !openDatesExpanded;
                    renderOpenDates();
                });
                grid.appendChild(toggle);
            }
        }
        
        function updateTotal() {
            let total = 0;
            document.querySelectorAll('input[name="lab_tests[]"]').forEach(cb => {
                const card = cb.closest('.selection-card');
                if (card) card.classList.toggle('selected', cb.checked);
            });
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
                updateConfirmEnabled();
                return;
            }

            const differentWa = document.getElementById('different_whatsapp')?.checked;
            const waInput = document.getElementById('whatsapp_phone');
            const waError = document.getElementById('whatsapp-error');
            if (differentWa) {
                const wa = waInput.value.trim();
                if (!isValidBdPhone(wa)) {
                    waError.innerText = config.whatsappInvalid;
                    waError.style.display = 'block';
                    updateConfirmEnabled();
                    return;
                }
            }

            if (!identityFieldsComplete()) {
                updateConfirmEnabled();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = config.bookingLabel;
            
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('bookable_type', state.type);
            formData.append('bookable_id', state.bookableId);
            formData.append('booking_date', document.getElementById('booking_date').value);
            formData.append('patient_phone', phone);
            const patientId = document.getElementById('patient_id').value;
            if (patientId) {
                // Existing household member: the id is the identity. We only
                // ever saw masked initials, so there is no name to send.
                formData.append('patient_id', patientId);
            } else {
                formData.append('patient_name', document.getElementById('patient_name').value);
            }
            if (state.type === 'lab') {
                document.querySelectorAll('input[name="lab_tests[]"]:checked').forEach(cb => {
                    formData.append('lab_tests[]', cb.value);
                });
            }
            // Always send — unchecked checkboxes are omitted from FormData, and
            // the server would treat absence as "default ON".
            formData.append(
                'share_clinical_history',
                document.getElementById('share_clinical_history')?.checked ? '1' : '0',
            );
            const nidValue = document.getElementById('patient_nid')?.value.trim();
            if (nidValue) {
                formData.append('nid', nidValue);
            }
            const yearOfBirthValue = document.getElementById('patient_year_of_birth')?.value.trim();
            if (yearOfBirthValue) {
                formData.append('year_of_birth', yearOfBirthValue);
            }
            if (differentWa && waInput.value.trim()) {
                formData.append('whatsapp_phone', waInput.value.trim());
            }
            
            try {
                const response = await fetch(`${config.basePath}/api/bookings`, {
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
                    const comeAroundEl = document.getElementById('comeAroundHint');
                    if (comeAroundEl) {
                        if (data.booking.overflow_phrase) {
                            comeAroundEl.textContent = data.booking.overflow_phrase;
                            comeAroundEl.classList.remove('hidden');
                        } else if (data.booking.come_around) {
                            comeAroundEl.textContent = @json(__('Come around :time', ['time' => '__TIME__'])).replace('__TIME__', data.booking.come_around);
                            comeAroundEl.classList.remove('hidden');
                        } else {
                            comeAroundEl.classList.add('hidden');
                        }
                    }
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
                    submitBtn.textContent = config.confirmLabel;
                    updateConfirmEnabled();

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
                submitBtn.textContent = config.confirmLabel;
                updateConfirmEnabled();
            }
        });

        // Prefill is resolved server-side (BookingController::prefillFrom) from
        // either the query string — deep links like ?doctor=ID / ?test=ID — or
        // the session, where the homepage hero POST stashes the patient's name
        // and phone so they never appear in the URL.
        const prefill = config.prefill || {};
        const doctorParam = prefill.doctor;
        const testParam = prefill.test;
        const sessionParam = prefill.session;
        state.prefilledDate = prefill.date || null;
        state.prefilledPhone = prefill.phone || null;
        state.prefilledName = prefill.name || null;

        if (sessionParam) {
            const session = sessionsData.find(s => String(s.id) === String(sessionParam));
            if (session) {
                state.type = 'session';
                state.typeLocked = true;
                state.doctorId = String(session.doctor_id);
                state.chamberId = String(session.chamber_id);
                state.bookableId = String(session.id);
                state.dayOfWeek = session.day_of_week;
                state.sessionName = session.session_name;
                state.startTime = session.start_time;
                state.endTime = session.end_time;
                state.doctorName = session.doctor?.name;
                state.chamberName = session.chamber?.name;
            }
        }
        if (doctorParam && config.doctorIds.includes(String(doctorParam))) {
            state.type = 'session';
            state.typeLocked = true;
            state.doctorId = String(doctorParam);
            const doctorSessions = sessionsData.filter(s => String(s.doctor_id) === String(doctorParam));
            const chamberIds = [...new Set(doctorSessions.map(s => String(s.chamber_id)))];
            if (chamberIds.length === 1) {
                state.chamberId = chamberIds[0];
            }
        }
        if (testParam) {
            state.type = 'lab';
            state.typeLocked = true;
            state.preselectedTestId = String(testParam);
        }

        rebuildFlow();

        if (state.bookableId && state.prefilledDate && state.prefilledPhone) {
            const identityIdx = flow.indexOf('step-identity');
            if (identityIdx >= 0) {
                currentStepIndex = identityIdx;
            }
        }

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
