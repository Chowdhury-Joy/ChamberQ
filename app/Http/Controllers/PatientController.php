<?php

namespace App\Http\Controllers;

use App\Services\PatientService;
use App\Support\BdPhone;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * Household members on a phone number, for the public booking wizard.
     *
     * Unauthenticated by necessity — the wizard runs before any login. That
     * makes it a patient-name oracle keyed on a guessable BD mobile, so the
     * response carries **masked initials only** (`maskedPickerLabel()`), never
     * the stored name. The booking endpoint resolves the real name from
     * `patient_id` server-side, so the wizard never needs it.
     */
    public function lookupByPhone(Request $request, PatientService $patientService)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
        ], [
            'phone.regex' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.'),
        ]);

        $phone = BdPhone::normalize($validated['phone']);
        $patients = $patientService->patientsForPhone($phone);

        return response()->json([
            'phone' => $phone,
            'patients' => $patients->map(fn ($patient) => [
                'id' => $patient->id,
                'label' => $patient->maskedPickerLabel(),
            ]),
        ]);
    }
}
