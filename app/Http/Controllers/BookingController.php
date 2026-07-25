<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use Carbon\Carbon;
use App\Services\BookingService;
use Exception;

class BookingController extends Controller
{
    public function create()
    {
        $chambers = Chamber::all();
        $doctors = Doctor::all();
        $sessions = ScheduleSession::with(['chamber', 'doctor'])->get();

        return view('tenant.book', compact('chambers', 'doctors', 'sessions'));
    }

    public function store(Request $request, BookingService $bookingService)
    {
        $request->validate([
            'session_id' => 'required|exists:schedule_sessions,id',
            'booking_date' => 'required|date',
            'patient_name' => 'required|string|max:255',
            'patient_phone' => 'required|string|max:20',
        ]);

        try {
            $session = ScheduleSession::findOrFail($request->session_id);
            
            $booking = $bookingService->createBookingForSession(
                $session,
                $request->booking_date,
                $request->patient_name,
                $request->patient_phone
            );

            return response()->json([
                'success' => true,
                'booking' => $booking
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
