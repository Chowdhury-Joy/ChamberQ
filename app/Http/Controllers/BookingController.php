<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function create()
    {
        $chambers = Chamber::all();
        $doctors = Doctor::all();
        $sessions = ScheduleSession::with(['chamber', 'doctor'])->get();
        $labSlots = LabCollectionSlot::with('chamber')->get();
        $hasLabTests = tenant()->hasFeature('lab_tests');

        $canBookConsultation = $chambers->isNotEmpty()
            && $doctors->isNotEmpty()
            && $sessions->isNotEmpty();
        $canBookLab = $hasLabTests
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
        ]);
    }

    public function store(Request $request, BookingService $bookingService)
    {
        $maxDate = now()->addDays(60)->toDateString();

        $validated = $request->validate([
            'bookable_type' => 'required|in:session,lab',
            'bookable_id' => 'required|integer',
            'booking_date' => "required|date|after_or_equal:today|before_or_equal:{$maxDate}",
            'patient_name' => 'required|string|max:255',
            // Bangladeshi mobile: optional +88 prefix, then 01[3-9] and 8 digits.
            'patient_phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'patient_id' => 'nullable|uuid|exists:patients,id',
            // Deliberately NOT `exists:lab_tests,id` — that rule is not tenant
            // scoped and would accept another tenant's test ids. The service
            // resolves them through the tenant scope and rejects the booking if
            // any id fails to resolve.
            'lab_tests' => 'nullable|array',
            'lab_tests.*' => 'integer',
        ], [
            'booking_date.after_or_equal' => __('Please choose today or a future date.'),
            'booking_date.before_or_equal' => __('Please choose a date within the next 60 days.'),
            'patient_phone.regex' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.'),
        ]);

        // findOrFail runs through the tenant global scope, so an id belonging to
        // another tenant 404s rather than resolving.
        $bookable = $validated['bookable_type'] === 'session'
            ? ScheduleSession::findOrFail($validated['bookable_id'])
            : LabCollectionSlot::findOrFail($validated['bookable_id']);

        try {
            // Line items are attached inside the service transaction, so a
            // booking can never be persisted without the tests it was made for.
            $booking = $bookingService->createBookingForBookable(
                $bookable,
                $validated['booking_date'],
                $validated['patient_name'],
                $this->normalizeBdPhone($validated['patient_phone']),
                $validated['lab_tests'] ?? [],
                true,
                $validated['patient_id'] ?? null,
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
                'ticket_url' => tenant_web_route('bookings.show', $booking),
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
