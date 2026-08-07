<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    public function print(Request $request, Prescription $prescription): View
    {
        $user = $request->user();

        // Role AND practice — this route has no Filament panel guard, and the
        // session cookie is shared across every panel on the host. See
        // User::belongsToCurrentTenant().
        if (! $user?->canViewVisitNotes() || ! $user->belongsToCurrentTenant()) {
            abort(403);
        }

        $prescription->load([
            'items',
            'patient',
            'visitRecord.booking.bookable',
        ]);

        $booking = $prescription->visitRecord?->booking;

        ['doctor' => $doctor, 'chamber' => $chamber] = $prescription->resolveDoctorChamber();

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
