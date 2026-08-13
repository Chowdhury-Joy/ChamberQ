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
            'visitRecord.condition',
        ]);

        $booking = $prescription->visitRecord?->booking;
        $visitRecord = $prescription->visitRecord;

        ['doctor' => $doctor, 'chamber' => $chamber] = $prescription->resolveDoctorChamber();

        $missingRegistration = blank($doctor?->registration_number);

        // Doctor print only. Patient share/portal never pass this, so a
        // phone copy always keeps the letterhead.
        $onMyPaper = $request->boolean('paper');

        return view('tenant.prescriptions.print', [
            'prescription' => $prescription,
            'visitRecord' => $visitRecord,
            'patient' => $prescription->patient,
            'booking' => $booking,
            'doctor' => $doctor,
            'chamber' => $chamber,
            'tenant' => tenant(),
            'missingRegistration' => $missingRegistration,
            'onMyPaper' => $onMyPaper,
        ]);
    }
}
