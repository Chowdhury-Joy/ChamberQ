@php
    $tenant = tenant();
    $hasLabTests = $tenant->hasFeature('lab_tests');
    $hasMultipleDoctors = $tenant->hasFeature('multiple_doctors');
    $hasMultipleChambers = $tenant->hasFeature('multiple_chambers');
    $fontFamily = $tenant->font_family ?? 'Inter';
    $themeColor = $tenant->theme_color ?? '#0ea5e9';
    $fontUrl = match($fontFamily) {
        'Outfit' => 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap',
        'Roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
        'Hind Siliguri' => 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap',
        default => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $themeColor }}">
    <title>{{ __('Book Appointment') }} | {{ $tenant->displayName() }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $fontUrl }}">
    @if($tenant->favicon_url)
    <link rel="icon" href="{{ $tenant->favicon_url }}">
    @endif
    <link rel="stylesheet" href="/css/theme.css">
    <style>
        :root {
            --color-primary: {{ $themeColor }};
            --font-family-base: '{{ $fontFamily }}', system-ui, -apple-system, sans-serif;
        }
        body { font-family: var(--font-family-base); }
        .booking-container { max-width: 650px; margin: 3rem auto; background: var(--bg-surface); padding: 3rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); position: relative; }
        .booking-header { text-align: center; margin-bottom: 2.5rem; }
        .step { display: none; animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .step.active { display: block; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: none; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #f87171; display: block; }
        .field-error { color: #dc2626; font-size: 0.85rem; margin-top: 0.35rem; display: block; }
        .selection-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 1.5rem; }
        .selection-grid.list-view { grid-template-columns: 1fr; }
        .selection-card { border: 2px solid #e2e8f0; border-radius: var(--radius-md); padding: 1.25rem; cursor: pointer; transition: all 0.2s ease; background: #fff; text-align: left; }
        .selection-card:hover { border-color: #cbd5e1; box-shadow: var(--shadow-sm); }
        .selection-card.selected { border-color: var(--color-primary); background: #f0f9ff; box-shadow: 0 0 0 1px var(--color-primary); }
        .selection-card h4 { margin: 0 0 0.5rem 0; color: #0f172a; font-size: 1.1rem; }
        .selection-card p { margin: 0; color: #64748b; font-size: 0.9rem; line-height: 1.4; }
        .selection-card .price { color: var(--color-primary); font-weight: 600; font-size: 1.1rem; float: right; }
        .btn-group { display: flex; justify-content: space-between; margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem; }
        .btn-back { background: #f1f5f9; color: #475569; border: none; }
        .btn-back:hover { background: #e2e8f0; }
        .progress-bar { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 2rem; }
        .progress-dot { width: 8px; height: 8px; border-radius: 50%; background: #e2e8f0; transition: all 0.3s; }
        .progress-dot.active { background: var(--color-primary); transform: scale(1.3); }
        .progress-dot.completed { background: #94a3b8; }
        .lab-total { font-size: 1.25rem; font-weight: 600; text-align: right; margin-top: 1rem; padding-top: 1rem; border-top: 2px dashed #e2e8f0; color: #0f172a; }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand">
            @if($tenant->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->displayName() }}" style="height: 36px; vertical-align: middle;">
            @else
                {{ $tenant->displayName() }}
            @endif
        </a>
        <div class="navbar-nav">
            <a href="/">{{ __('Home') }}</a>
        </div>
    </nav>

    <main class="section">
        <div class="booking-container">
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
                        <div class="selection-card" onclick="selectType('session')">
                            <h4>{{ __('Doctor Consultation') }}</h4>
                            <p>{{ __('Book a visit with one of our specialists.') }}</p>
                        </div>
                        @if($hasLabTests)
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
                    <div class="btn-group">
                        <button type="button" class="btn btn-back" onclick="prevStep()">{{ __('Back') }}</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">{{ __('Continue') }}</button>
                    </div>
                </div>

                <!-- STEP 6: Identity & Date -->
                <div class="step" id="step-identity" data-step-name="identity">
                    <h3>{{ __('Patient Details') }}</h3>
                    
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
                <p class="text-muted">{{ __('Redirecting to your live queue ticket...') }}</p>
            </div>
        </div>
    </main>

    <!-- Data passed from backend -->
    <script>
        const config = {
            hasLabTests: @json($hasLabTests),
            hasMultipleDoctors: @json($hasMultipleDoctors),
            hasMultipleChambers: @json($hasMultipleChambers),
            chambersCount: @json(count($chambers)),
            doctorsCount: @json(count($doctors)),
        };
        
        const sessionsData = @json($sessions);
        const labSlotsData = @json($labSlots);
        const dayLabels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        let state = {
            type: null,
            chamberId: null,
            doctorId: null,
            bookableId: null,
            dayOfWeek: null
        };

        let flow = [];
        
        function rebuildFlow() {
            flow = [];
            
            if (config.hasLabTests) {
                flow.push('step-type');
            } else {
                state.type = 'session';
            }
            
            if (config.hasMultipleChambers && config.chambersCount > 1) {
                flow.push('step-chamber');
            } else {
                @if(count($chambers) > 0)
                state.chamberId = "{{ $chambers->first()->id }}";
                @endif
            }
            
            if (state.type !== 'lab' && config.hasMultipleDoctors && config.doctorsCount > 1) {
                flow.push('step-doctor');
            } else if (state.type !== 'lab') {
                @if(count($doctors) > 0)
                state.doctorId = "{{ $doctors->first()->id }}";
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
            
            // Today .. +60 days — matches server-side booking_date rules
            const today = new Date();
            dateInput.min = today.toISOString().split('T')[0];
            const max = new Date(today);
            max.setDate(max.getDate() + 60);
            dateInput.max = max.toISOString().split('T')[0];
            
            // Compute next valid date
            const nextValid = new Date(today);
            while (nextValid.getDay() !== state.dayOfWeek) {
                nextValid.setDate(nextValid.getDate() + 1);
            }
            const formatted = nextValid.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            document.getElementById('date-helper').innerText = `Next available: ${formatted}`;
            dateInput.value = nextValid.toISOString().split('T')[0];
            
            // Assign once so revisiting this step does not stack listeners
            dateInput.onchange = validateDate;
            dateError.style.display = 'none';
            submitBtn.disabled = false;
        }

        function validateDate() {
            const dateInput = document.getElementById('booking_date');
            const dateError = document.getElementById('date-error');
            const submitBtn = document.getElementById('submitBtn');
            
            if (!dateInput.value) {
                dateError.style.display = 'none';
                submitBtn.disabled = false;
                return;
            }
            
            const selected = new Date(dateInput.value + 'T00:00:00');
            const selectedDay = selected.getDay();
            
            if (selectedDay !== state.dayOfWeek) {
                const expected = dayLabels[state.dayOfWeek];
                const got = dayLabels[selectedDay];
                dateError.innerText = `This date is a ${got}. You need to pick a ${expected}.`;
                dateError.style.display = 'block';
                submitBtn.disabled = true;
            } else {
                dateError.style.display = 'none';
                submitBtn.disabled = false;
            }
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
        
        function selectBookable(id, dayOfWeek) {
            state.bookableId = id;
            state.dayOfWeek = dayOfWeek;
            document.querySelectorAll('#session-grid .selection-card').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            setTimeout(nextStep, 200);
        }
        
        function renderSessions() {
            const grid = document.getElementById('session-grid');
            const title = document.getElementById('session-title');
            grid.innerHTML = '';
            
            if (state.type === 'session') {
                title.innerText = 'Select a Schedule';
                const filtered = sessionsData.filter(s => 
                    (!state.chamberId || s.chamber_id == state.chamberId) && 
                    (!state.doctorId || s.doctor_id == state.doctorId)
                );
                
                if (filtered.length === 0) {
                    grid.innerHTML = '<p class="text-muted">No schedules found for this selection.</p>';
                    return;
                }
                
                filtered.forEach(s => {
                    const tStart = s.start_time.substring(0, 5);
                    const tEnd = s.end_time.substring(0, 5);
                    const day = dayLabels[s.day_of_week];
                    grid.innerHTML += `
                        <div class="selection-card" onclick="selectBookable('${s.id}', ${s.day_of_week})">
                            <h4>${s.session_name}</h4>
                            <p>${day}s • ${tStart} - ${tEnd}</p>
                            <p class="text-muted" style="font-size:0.8rem; margin-top:0.5rem;">Doctor: ${s.doctor.name} | Chamber: ${s.chamber.name}</p>
                        </div>
                    `;
                });
            } else {
                title.innerText = 'Select a Lab Collection Window';
                const filtered = labSlotsData.filter(s => 
                    (!state.chamberId || s.chamber_id == state.chamberId)
                );
                
                if (filtered.length === 0) {
                    grid.innerHTML = '<p class="text-muted">No lab slots found for this chamber.</p>';
                    return;
                }
                
                filtered.forEach(s => {
                    const tStart = s.start_time.substring(0, 5);
                    const tEnd = s.end_time.substring(0, 5);
                    const day = dayLabels[s.day_of_week];
                    const chamberName = s.chamber ? s.chamber.name : 'Main Lab';
                    grid.innerHTML += `
                        <div class="selection-card" onclick="selectBookable('${s.id}', ${s.day_of_week})">
                            <h4>${day}s</h4>
                            <p>${tStart} - ${tEnd} • ${chamberName}</p>
                        </div>
                    `;
                });
            }
        }
        
        function updateTotal() {
            let total = 0;
            document.querySelectorAll('input[name="lab_tests[]"]:checked').forEach(cb => {
                total += parseFloat(cb.dataset.price);
            });
            document.getElementById('labTotalAmount').innerText = '৳' + total.toFixed(2);
        }

        document.getElementById('bookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msgEl = document.getElementById('message');
            const submitBtn = document.getElementById('submitBtn');
            msgEl.className = 'alert';
            msgEl.style.display = 'none';
            msgEl.innerText = '';
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('bookable_type', state.type);
            formData.append('bookable_id', state.bookableId);
            formData.append('booking_date', document.getElementById('booking_date').value);
            formData.append('patient_name', document.getElementById('patient_name').value);
            formData.append('patient_phone', document.getElementById('patient_phone').value);
            
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
                    
                    setTimeout(() => {
                        window.location.href = data.booking.ticket_url;
                    }, 2500);
                } else {
                    let message = data.message || 'Validation error. Please check your inputs.';
                    if (data.errors) {
                        const first = Object.values(data.errors).flat()[0];
                        if (first) message = first;
                    }
                    msgEl.innerText = message;
                    msgEl.className = 'alert alert-error';
                    msgEl.style.display = 'block';
                    submitBtn.disabled = false;
                }
            } catch (err) {
                msgEl.innerText = 'An error occurred submitting the booking.';
                msgEl.className = 'alert alert-error';
                msgEl.style.display = 'block';
                submitBtn.disabled = false;
            }
        });

        // Deep link support: ?doctor=ID (also accept legacy ?doctor_id=) or ?test=ID
        const params = new URLSearchParams(window.location.search);
        const doctorParam = params.get('doctor') || params.get('doctor_id');
        if (doctorParam) {
            state.type = 'session';
            state.doctorId = doctorParam;
        }
        if (params.has('test')) {
            state.type = 'lab';
        }

        rebuildFlow();
        showStep();
    </script>
</body>
</html>
