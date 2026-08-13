<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\PlatformPatientHistoryService;
use Illuminate\View\View;

class PatientAccountController extends Controller
{
    public function serials(PlatformPatientHistoryService $history): View
    {
        $account = auth('patient')->user();

        return view('patient.serials', [
            'account' => $account,
            'serials' => $history->serialsFor($account),
        ]);
    }

    public function history(PlatformPatientHistoryService $history): View
    {
        $account = auth('patient')->user();

        return view('patient.history', [
            'account' => $account,
            'visits' => $history->historyFor($account),
        ]);
    }

    public function showPrescription(string $prescription, PlatformPatientHistoryService $history): View
    {
        $account = auth('patient')->user();
        $rx = $history->prescriptionForAccount($account, $prescription);

        $tenant = Tenant::query()->findOrFail($rx->tenant_id);
        tenancy()->initialize($tenant);

        ['doctor' => $doctor, 'chamber' => $chamber] = $rx->resolveDoctorChamber();

        $visit = $rx->visitRecord;

        return view('tenant.prescriptions.share', [
            'prescription' => $rx,
            'visitRecord' => $visit,
            'patient' => $rx->patient,
            'booking' => $visit?->booking,
            'doctor' => $doctor,
            'chamber' => $chamber,
            'tenant' => $tenant,
            'showExpiryFootnote' => false,
        ]);
    }
}
