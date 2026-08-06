<?php

namespace App\Http\Controllers;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Prescription;
use App\Models\ScheduleSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    public function print(Request $request, Prescription $prescription): View
    {
        $user = $request->user();

        if (! $user?->canViewVisitNotes()) {
            abort(403);
        }

        $prescription->load([
            'items',
            'patient',
            'visitRecord.booking.bookable',
        ]);

        $booking = $prescription->visitRecord?->booking;
        $doctor = null;
        $chamber = null;

        if ($booking && $booking->bookable_type === ScheduleSession::class) {
            $session = ScheduleSession::with(['doctor', 'chamber'])->find($booking->bookable_id);
            $doctor = $session?->doctor;
            $chamber = $session?->chamber;
        }

        if (! $chamber) {
            $chamber = Chamber::query()->first();
        }

        if (! $doctor) {
            $doctor = Doctor::query()->first();
        }

        $missingRegistration = blank($doctor?->registration_number);

        return view('tenant.prescriptions.print', [
            'prescription' => $prescription,
            'patient' => $prescription->patient,
            'booking' => $booking,
            'doctor' => $doctor,
            'chamber' => $chamber,
            'tenant' => tenant(),
            'missingRegistration' => $missingRegistration,
        ]);
    }
}
