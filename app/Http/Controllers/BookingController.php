<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LabCollectionSlot;
use App\Models\Patient;
use App\Models\ScheduleSession;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * Prefill keys the booking wizard understands, mapped to their aliases.
     *
     * `name` and `phone` are patient PII and must never travel in a URL — see
     * `prefill()`. The rest are safe to deep-link.
     */
    private const PREFILL_KEYS = [
        'doctor' => ['doctor', 'doctor_id'],
        'test' => ['test'],
        'session' => ['session', 'bookable_id'],
        'date' => ['date', 'booking_date'],
        'name' => ['name', 'patient_name'],
        'phone' => ['phone', 'patient_phone'],
    ];

    /**
     * Landing point for the homepage hero form.
     *
     * The hero used to GET straight to `/book`, which put the patient's name
     * and phone number in the address bar — and therefore in browser history,
     * server access logs, and the `Referer` header of every asset the booking
     * page loads. It POSTs now; this stashes the values in the session and
     * redirects, so the wizard receives them with a clean URL (Post/Redirect/
     * Get, which also means a refresh does not re-submit).
     */
    public function prefill(Request $request)
    {
        session()->flash('booking_prefill', $this->prefillFrom($request));

        return redirect()->to(tenant_web_url('/book'));
    }

    public function create(Request $request)
    {
        $chambers = Chamber::all();
        $doctors = Doctor::all();
        $sessions = ScheduleSession::with(['chamber', 'doctor'])->get();
        $labSlots = LabCollectionSlot::with('chamber')->get();
        $hasLabTests = tenant()->hasFeature('lab_tests');

        // Billing state is checked here, not only on POST. It gates the same
        // thing either way, but a patient who fills in a doctor, a session, a
        // date, their name and their phone before being told booking is closed
        // has been wasted; the wizard's own empty state says it up front.
        $acceptsBookings = tenant()?->acceptsBookings() ?? true;

        $canBookConsultation = $acceptsBookings
            && $chambers->isNotEmpty()
            && $doctors->isNotEmpty()
            && $sessions->isNotEmpty();
        $canBookLab = $acceptsBookings
            && $hasLabTests
            && $chambers->isNotEmpty()
            && $labSlots->isNotEmpty();

        $view = tenant()?->isSoloDoctor()
            ? 'tenant.solo.book'
            : 'tenant.book';

        return view($view, [
            'chambers' => $chambers,
            'doctors' => $doctors,
            'sessions' => $sessions,
            'labSlots' => $labSlots,
            // Only offered when the tenant actually has the capability, and
            // only tests the clinic has left switched on.
            'labTests' => $hasLabTests
                ? \App\Models\LabTest::active()->ordered()->get()
                : collect(),
            'bookingAvailable' => $canBookConsultation || $canBookLab,
            'canBookConsultation' => $canBookConsultation,
            'canBookLab' => $canBookLab,
            // Lets the empty state say "call the clinic" rather than the
            // schedules-not-published copy, which would be a lie here.
            'bookingClosedForBilling' => ! $acceptsBookings,
            // Session values (from the hero POST) win over query params, so a
            // deep link still works but PII never has to ride in the URL.
            'prefill' => array_merge(
                $this->prefillFrom($request),
                (array) session('booking_prefill', []),
            ),
        ]);
    }

    /**
     * Pull the wizard's prefill values out of a request, whichever alias and
     * whichever method (query string or POST body) they arrived under.
     *
     * @return array<string, string>
     */
    private function prefillFrom(Request $request): array
    {
        $prefill = [];

        foreach (self::PREFILL_KEYS as $key => $aliases) {
            foreach ($aliases as $alias) {
                $value = $request->input($alias);

                if (filled($value) && is_string($value)) {
                    $prefill[$key] = $value;
                    break;
                }
            }
        }

        return $prefill;
    }

    public function store(Request $request, BookingService $bookingService)
    {
        $maxDate = now()->addDays(60)->toDateString();

        $validated = $request->validate([
            'bookable_type' => 'required|in:session,lab',
            'bookable_id' => 'required|integer',
            'booking_date' => "required|date|after_or_equal:today|before_or_equal:{$maxDate}",
            // Optional only when an existing household member was picked — the
            // wizard is shown masked initials for those, so it has no real name
            // to submit and the server reads it off the patient record instead.
            'patient_name' => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            // Bangladeshi mobile: optional +88 prefix, then 01[3-9] and 8 digits.
            'patient_phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            // Deliberately NOT `exists:patients,id` — same reason as lab tests
            // below: that rule is not tenant scoped. Resolved against this
            // tenant AND this phone number a few lines down instead.
            'patient_id' => ['nullable', 'uuid'],
            // Deliberately NOT `exists:lab_tests,id` — that rule is not tenant
            // scoped and would accept another tenant's test ids. The service
            // resolves them through the tenant scope and rejects the booking if
            // any id fails to resolve.
            'lab_tests' => 'nullable|array',
            'lab_tests.*' => 'integer',
            'wants_earlier_date' => 'nullable|boolean',
            'whatsapp_phone' => ['nullable', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
        ], [
            'booking_date.after_or_equal' => __('Please choose today or a future date.'),
            'booking_date.before_or_equal' => __('Please choose a date within the next 60 days.'),
            'patient_phone.regex' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.'),
            'whatsapp_phone.regex' => __('Please enter a valid Bangladeshi WhatsApp number, for example 01712345678.'),
        ]);

        // findOrFail runs through the tenant global scope, so an id belonging to
        // another tenant 404s rather than resolving.
        $bookable = $validated['bookable_type'] === 'session'
            ? ScheduleSession::findOrFail($validated['bookable_id'])
            : LabCollectionSlot::findOrFail($validated['bookable_id']);

        $normalizedPhone = $this->normalizeBdPhone($validated['patient_phone']);

        // A supplied patient_id only counts if it belongs to this tenant (global
        // scope) AND to the phone number being booked with. Anything else is
        // treated as "nobody picked", which puts the name back in play.
        $chosenPatient = filled($validated['patient_id'] ?? null)
            ? Patient::query()
                ->whereKey($validated['patient_id'])
                ->where('phone', $normalizedPhone)
                ->first()
            : null;

        if (! $chosenPatient && blank($validated['patient_name'] ?? null)) {
            throw ValidationException::withMessages([
                'patient_name' => __('Please enter the patient name.'),
            ]);
        }

        try {
            // Line items are attached inside the service transaction, so a
            // booking can never be persisted without the tests it was made for.
            $booking = $bookingService->createBookingForBookable(
                $bookable,
                $validated['booking_date'],
                (string) ($validated['patient_name'] ?? ''),
                $normalizedPhone,
                $validated['lab_tests'] ?? [],
                true,
                $chosenPatient?->id,
                (bool) ($validated['wants_earlier_date'] ?? false),
                $validated['whatsapp_phone'] ?? null,
            );
        } catch (BookingUnavailableException $e) {
            // Only this exception type is safe to echo back to an anonymous
            // visitor. Anything else is a genuine fault and must surface as a
            // 500 so it is logged rather than disguised as a validation error.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'booking' => [
                'id' => $booking->id,
                'serial_number' => $booking->serial_number,
                // Relative on purpose: absolute URLs bake in whatever host Laravel
                // thinks it has (often localhost behind a reverse proxy). The
                // wizard already runs on the patient's real domain, so a path
                // keeps them there.
                'ticket_url' => tenant_web_route('bookings.show', $booking, absolute: false),
            ],
        ]);
    }

    /**
     * Seats left / blocked for Fatima’s chosen date before she hits Confirm.
     */
    public function availability(Request $request, BookingService $bookingService)
    {
        $maxDate = now()->addDays(60)->toDateString();

        $validated = $request->validate([
            'bookable_type' => 'required|in:session,lab',
            'bookable_ids' => 'required|array|min:1|max:50',
            'bookable_ids.*' => 'integer',
            'booking_date' => "required|date|after_or_equal:today|before_or_equal:{$maxDate}",
        ]);

        $modelClass = $validated['bookable_type'] === 'session'
            ? ScheduleSession::class
            : LabCollectionSlot::class;

        $bookables = $modelClass::whereIn('id', $validated['bookable_ids'])->get()->keyBy('id');

        $items = [];
        foreach ($validated['bookable_ids'] as $id) {
            $bookable = $bookables->get($id);
            if (! $bookable) {
                $items[(string) $id] = [
                    'available' => false,
                    'blocked' => false,
                    'day_mismatch' => false,
                    'cap' => 0,
                    'booked' => 0,
                    'remaining' => 0,
                    'missing' => true,
                ];
                continue;
            }

            $snapshot = $bookingService->availabilityFor($bookable, $validated['booking_date']);
            $items[(string) $id] = array_merge($snapshot, ['missing' => false]);
        }

        return response()->json([
            'date' => $validated['booking_date'],
            'items' => $items,
        ]);
    }

    /**
     * Open dates for the booking wizard's "when can you come?" step.
     */
    public function openDates(Request $request, BookingService $bookingService)
    {
        $validated = $request->validate([
            'bookable_type' => 'required|in:session,lab',
            'bookable_ids' => 'required|array|min:1|max:50',
            'bookable_ids.*' => 'integer',
        ]);

        $modelClass = $validated['bookable_type'] === 'session'
            ? ScheduleSession::class
            : LabCollectionSlot::class;

        $with = $validated['bookable_type'] === 'session'
            ? ['chamber', 'doctor']
            : ['chamber'];

        $bookables = $modelClass::with($with)
            ->whereIn('id', $validated['bookable_ids'])
            ->get();

        $open = $bookingService->openDatesFor($bookables);

        $bookablesById = $bookables->keyBy('id');
        $options = [];

        foreach ($open as $row) {
            $bookable = $bookablesById->get($row['bookable_id']);
            if (! $bookable) {
                continue;
            }

            $option = [
                'bookable_id' => (string) $bookable->id,
                'date' => $row['date'],
                'remaining' => $row['remaining'],
                'cap' => $row['cap'],
                'booked' => $row['booked'],
                'start_time' => $bookable->start_time,
                'end_time' => $bookable->end_time,
                'day_of_week' => (int) $bookable->day_of_week,
            ];

            if ($bookable instanceof ScheduleSession) {
                $option['session_name'] = $bookable->session_name;
                $option['doctor'] = $bookable->doctor ? ['name' => $bookable->doctor->name] : null;
                $option['chamber'] = $bookable->chamber ? ['name' => $bookable->chamber->name] : null;
            } else {
                $option['session_name'] = null;
                $option['doctor'] = null;
                $option['chamber'] = $bookable->chamber ? ['name' => $bookable->chamber->name] : null;
            }

            $options[] = $option;
        }

        return response()->json([
            'options' => $options,
            'has_open_dates' => $options !== [],
        ]);
    }

    public function show(Booking $booking)
    {
        $booking->load([
            'labTests',
            'bookable' => function ($morphTo) {
                $morphTo->morphWith([
                    ScheduleSession::class => ['chamber', 'doctor'],
                    LabCollectionSlot::class => ['chamber'],
                ]);
            },
        ]);

        return view(tenant()?->isSoloDoctor() ? 'tenant.solo.ticket' : 'tenant.ticket', [
            'booking' => $booking,
        ]);
    }

    public function portal(Request $request)
    {
        $phone = $request->query('phone');
        $bookings = collect();
        $error = null;

        if (filled($phone)) {
            $validator = validator(
                ['phone' => $phone],
                ['phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/']],
                ['phone.regex' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.')]
            );

            if ($validator->fails()) {
                $error = $validator->errors()->first('phone');
            } else {
                $variants = $this->phoneLookupVariants((string) $phone);

                $bookings = Booking::whereIn('patient_phone', $variants)
                    ->with(['bookable'])
                    ->latest()
                    ->take(10)
                    ->get();
            }
        }

        // Per-tier view, same split as index() and show(): the clinic portal
        // follows the Clireo design, solo keeps its own locked look.
        return view(tenant()?->isSoloDoctor() ? 'tenant.solo.portal.index' : 'tenant.portal.index', [
            'bookings' => $bookings,
            'phone' => $phone,
            'error' => $error,
        ]);
    }

    /**
     * Store as 01XXXXXXXXX so portal lookup with/without +88 still matches.
     */
    private function normalizeBdPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '88')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Canonicalise a BD mobile so portal lookup matches how the number was typed at booking.
     *
     * @return list<string>
     */
    private function phoneLookupVariants(string $phone): array
    {
        $digits = $this->normalizeBdPhone($phone);

        return array_values(array_unique([
            $digits,
            '88' . $digits,
            '+88' . $digits,
            trim($phone),
        ]));
    }
}
