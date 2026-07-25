<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\LabCollectionSlot;
use App\Services\BookingService;
use Exception;

class BookingController extends Controller
{
    public function create()
    {
        $chambers = Chamber::all();
        $doctors = Doctor::all();
        $sessions = ScheduleSession::with(['chamber', 'doctor'])->get();
        $labSlots = LabCollectionSlot::with('chamber')->get();

        return view('tenant.book', compact('chambers', 'doctors', 'sessions', 'labSlots'));
    }

    public function store(Request $request, BookingService $bookingService)
    {
        $request->validate([
            'bookable_type' => 'required|in:session,lab',
            'bookable_id' => 'required|integer',
            'booking_date' => 'required|date',
            'patient_name' => 'required|string|max:255',
            'patient_phone' => 'required|string|max:20',
        ]);

        try {
            if ($request->bookable_type === 'session') {
                $bookable = ScheduleSession::findOrFail($request->bookable_id);
            } else {
                $bookable = LabCollectionSlot::findOrFail($request->bookable_id);
            }
            
            $booking = $bookingService->createBookingForBookable(
                $bookable,
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
