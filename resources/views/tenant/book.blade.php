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
            <a href="/">Home</a>
        </div>
    </nav>

    <main class="section">
        <div class="booking-container">
            <div class="booking-header">
                <h2>Book Your Appointment</h2>
                <p class="text-muted">Fill out the details below to secure your slot.</p>
            </div>
            
            <div id="message" class="alert hidden"></div>

            <form id="bookingForm">
                @csrf
                <div class="form-group">
                    <label class="form-label">Select Session</label>
                    <select name="session_id" id="session_id" class="form-control" required>
                        <option value="">-- Choose a session --</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" data-day="{{ $session->day_of_week }}">
                                {{ $session->doctor->name }} at {{ $session->chamber->name }} ({{ $session->session_name }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="booking_date" id="booking_date" class="form-control" required>
                    <small class="text-muted" style="display:block;margin-top:0.5rem">Dates must match the session's designated day of the week.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Patient Name</label>
                    <input type="text" name="patient_name" id="patient_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="patient_phone" id="patient_phone" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Confirm Booking</button>
            </form>
            
            <div id="successView" class="hidden" style="text-align: center;">
                <h3>Booking Confirmed!</h3>
                <p>Your serial number is: <strong id="serialBadge" style="font-size: 2rem; color: var(--color-primary); display:block; margin: 1rem 0;"></strong></p>
                <p>Save this URL to check live queue status:</p>
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
                    const queueUrl = window.location.origin + '/api/queue/' + data.booking.bookable_id + '/' + data.booking.booking_date;
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
    </script>
</body>
</html>
