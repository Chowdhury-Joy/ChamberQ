<?php

namespace App\Http\Controllers;

use App\Services\PatientService;
use App\Support\BdPhone;
use Illuminate\Http\Request;

class PatientController extends Controller
{
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
                'name' => $patient->name,
                'label' => $patient->pickerLabel(),
                'visit_count' => $patient->visit_count,
            ]),
        ]);
    }
}
