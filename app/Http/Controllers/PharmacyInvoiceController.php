<?php

namespace App\Http\Controllers;

use App\Models\PharmacySale;
use App\Models\User;
use App\Services\PharmacyInvoiceService;
use App\Support\PharmacyAccess;
use App\Support\StaffDeskScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyInvoiceController extends Controller
{
    public function show(Request $request, PharmacySale $sale): View
    {
        $user = $request->user();

        if (! $user instanceof User
            || ! $user->belongsToCurrentTenant()
            || ! PharmacyAccess::canRunCounter($user)) {
            abort(403);
        }

        if ($sale->tenant_id !== tenant()?->id) {
            abort(404);
        }

        $sale->load(['items.item']);
        $item = $sale->items->first()?->item;
        if ($item !== null && ! StaffDeskScope::pharmacyItemIsVisible($user, $item)) {
            abort(403);
        }

        return view('tenant.pharmacy-invoice', app(PharmacyInvoiceService::class)->viewData($sale));
    }
}
