<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0ea5e9">
    <title>Book Appointment | {{ tenant('id') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="/css/theme.css">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    </script>
    <style>
        .booking-container { max-width: 600px; margin: 4rem auto; background: var(--bg-surface); padding: 3rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); }
        .booking-header { text-align: center; margin-bottom: 2rem; }
        .hidden { display: none; }
        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #f87171; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #4ade80; }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand">{{ tenant('id') }}</a>
        <div class="navbar-nav">
            <a href="/">{{ __('Home') }}</a>
            <a href="/lang/en" style="margin-left: 1rem; font-size: 0.8rem;">EN</a>
            <a href="/lang/bn" style="margin-left: 0.5rem; font-size: 0.8rem;">BN</a>
        </div>
    </nav>

    <main class="section">
        <div class="booking-container">
            <div class="booking-header">
                <h2>{{ __('Book Your Appointment') }}</h2>
                <p class="text-muted">{{ __('Fill out the details below to secure your slot.') }}</p>
            </div>
            
            <div id="message" class="alert hidden"></div>

            <form id="bookingForm">
                @csrf
                <div class="form-group">
                    <label class="form-label">{{ __('Booking Type') }}</label>
                    <select name="bookable_type" id="bookable_type" class="form-control" required onchange="toggleBookingType()">
                        <option value="session">{{ __('Doctor Appointment') }}</option>
                        <option value="lab">{{ __('Lab Test') }}</option>
                    </select>
                </div>

                <div class="form-group" id="sessionSelectGroup">
                    <label class="form-label">{{ __('Select Doctor Session') }}</label>
                    <select name="session_id" id="session_id" class="form-control">
                        <option value="">{{ __('-- Choose a session --') }}</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" data-day="{{ $session->day_of_week }}">
                                {{ $session->doctor->name }} at {{ $session->chamber->name }} ({{ $session->session_name }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group hidden" id="labSelectGroup">
                    <label class="form-label">{{ __('Select Lab Collection Slot') }}</label>
                    <select name="lab_id" id="lab_id" class="form-control">
                        <option value="">{{ __('-- Choose a lab slot --') }}</option>
                        @foreach($labSlots as $slot)
                            <option value="{{ $slot->id }}" data-day="{{ $slot->day_of_week }}">
                                {{ ucfirst($slot->day_of_week) }} ({{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}) at {{ $slot->chamber ? $slot->chamber->name : 'Main Lab' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('Date') }}</label>
                    <input type="date" name="booking_date" id="booking_date" class="form-control" required>
                    <small class="text-muted" style="display:block;margin-top:0.5rem">{{ __('Dates must match the session\'s designated day of the week.') }}</small>
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('Patient Name') }}</label>
                    <input type="text" name="patient_name" id="patient_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('Phone Number') }}</label>
                    <input type="tel" name="patient_phone" id="patient_phone" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">{{ __('Confirm Booking') }}</button>
            </form>
            
            <div id="successView" class="hidden" style="text-align: center;">
                <h3>{{ __('Booking Confirmed!') }}</h3>
                <p>{{ __('Your serial number is:') }} <strong id="serialBadge" style="font-size: 2rem; color: var(--color-primary); display:block; margin: 1rem 0;"></strong></p>
                <p>{{ __('Save this URL to check live queue status:') }}</p>
                <a id="queueLink" href="#" style="word-break: break-all;"></a>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('bookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msgEl = document.getElementById('message');
            msgEl.className = 'alert hidden';
            
            const formData = new FormData(this);
            const bookableType = formData.get('bookable_type');
            if (bookableType === 'session') {
                formData.set('bookable_id', formData.get('session_id'));
            } else {
                formData.set('bookable_id', formData.get('lab_id'));
            }
            formData.delete('session_id');
            formData.delete('lab_id');
            
            try {
                const response = await fetch('/api/bookings', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    this.classList.add('hidden');
                    document.getElementById('successView').classList.remove('hidden');
                    document.getElementById('serialBadge').innerText = data.booking.serial_number;
                    const queueUrl = window.location.origin + '/api/queue/' + bookableType + '/' + data.booking.bookable_id + '/' + data.booking.booking_date;
                    document.getElementById('queueLink').href = queueUrl;
                    document.getElementById('queueLink').innerText = queueUrl;
                } else {
                    msgEl.innerText = data.message || 'Validation error.';
                    msgEl.className = 'alert alert-error';
                }
            } catch (err) {
                msgEl.innerText = 'An error occurred submitting the booking.';
                msgEl.className = 'alert alert-error';
            }
        });
        
        function toggleBookingType() {
            const type = document.getElementById('bookable_type').value;
            const sessionGroup = document.getElementById('sessionSelectGroup');
            const labGroup = document.getElementById('labSelectGroup');
            const sessionSelect = document.getElementById('session_id');
            const labSelect = document.getElementById('lab_id');
            
            if (type === 'session') {
                sessionGroup.classList.remove('hidden');
                labGroup.classList.add('hidden');
                sessionSelect.required = true;
                labSelect.required = false;
            } else {
                sessionGroup.classList.add('hidden');
                labGroup.classList.remove('hidden');
                sessionSelect.required = false;
                labSelect.required = true;
            }
        }
        
        // Initialize state
        toggleBookingType();
    </script>
</body>
</html>
