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
        return view('tenant.book', [
            'chambers' => Chamber::all(),
            'doctors' => Doctor::all(),
            'sessions' => ScheduleSession::with(['chamber', 'doctor'])->get(),
            'labSlots' => LabCollectionSlot::with('chamber')->get(),
            // Only offered when the tenant actually has the capability, and
            // only tests the clinic has left switched on.
            'labTests' => tenant()->hasFeature('lab_tests')
                ? \App\Models\LabTest::active()->ordered()->get()
                : collect(),
        ]);
    }

    public function store(Request $request, BookingService $bookingService)
    {
        $validated = $request->validate([
            'bookable_type' => 'required|in:session,lab',
            'bookable_id' => 'required|integer',
            'booking_date' => 'required|date',
            'patient_name' => 'required|string|max:255',
            // Bangladeshi mobile: optional +88 prefix, then 01[3-9] and 8 digits.
            'patient_phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            // Deliberately NOT `exists:lab_tests,id` — that rule is not tenant
            // scoped and would accept another tenant's test ids. The service
            // resolves them through the tenant scope and rejects the booking if
            // any id fails to resolve.
            'lab_tests' => 'nullable|array',
            'lab_tests.*' => 'integer',
        ], [
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
                $validated['patient_phone'],
                $validated['lab_tests'] ?? []
            );
        } catch (BookingUnavailableException $e) {
            // Only this exception type is safe to echo back to an anonymous
            // visitor. Anything else is a genuine fault and must surface as a
            // 500 so it is logged rather than disguised as a validation error.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'booking' => [
                'id' => $booking->id,
                'serial_number' => $booking->serial_number,
                'ticket_url' => route('bookings.show', $booking),
            ],
        ]);
    }

    public function show(Booking $booking)
    {
        $booking->load(['bookable', 'labTests']);

        return view('tenant.ticket', [
            'booking' => $booking,
        ]);
    }

    public function portal(Request $request)
    {
        $phone = $request->query('phone');
        $bookings = collect();

        if (filled($phone)) {
            $bookings = Booking::where('patient_phone', 'like', '%' . trim((string) $phone) . '%')
                ->with(['bookable'])
                ->latest()
                ->take(10)
                ->get();
        }

        return view('tenant.portal.index', [
            'bookings' => $bookings,
            'phone' => $phone,
        ]);
    }
}
