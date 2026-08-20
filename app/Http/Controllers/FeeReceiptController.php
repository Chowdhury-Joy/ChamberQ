<?php

namespace App\Http\Controllers;

use App\Models\ChamberCashEntry;
use App\Models\User;
use App\Services\FeeReceiptService;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeeReceiptController extends Controller
{
    public function show(Request $request, ChamberCashEntry $entry): View
    {
        $user = $request->user();

        if (! $user instanceof User
            || ! $user->belongsToCurrentTenant()
            || ! StaffDeskJobs::canCollectFee($user)) {
            abort(403);
        }

        if ($entry->tenant_id !== tenant()?->id || ! $entry->isPatientFeeReceipt()) {
            abort(404);
        }

        if (! StaffDeskScope::cashEntryIsVisible($user, $entry)) {
            abort(403);
        }

        return view('tenant.fee-receipt', app(FeeReceiptService::class)->viewData($entry));
    }
}
