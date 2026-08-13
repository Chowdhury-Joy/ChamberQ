<?php

namespace App\Http\Controllers;

use App\Services\MedicineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function search(Request $request, MedicineService $medicineService): JsonResponse
    {
        $user = $request->user();

        // Results are ranked by this practice's own prescribing history, so the
        // tenant half of the check matters here too. See
        // User::belongsToCurrentTenant().
        if (! $user?->canRecordVisitNotes() || ! $user->belongsToCurrentTenant()) {
            abort(403);
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
        ]);

        $results = $medicineService->search($validated['q'], $user);

        return response()->json([
            'query' => $validated['q'],
            'results' => $results,
        ]);
    }

    /**
     * The strengths one brand actually ships in, for the Rx desk dose chips.
     *
     * Separate from `search()` on purpose: the desk needs these for rows it
     * loaded from an existing prescription too, where no search ever ran.
     */
    public function doses(Request $request, MedicineService $medicineService): JsonResponse
    {
        $user = $request->user();

        if (! $user?->canRecordVisitNotes() || ! $user->belongsToCurrentTenant()) {
            abort(403);
        }

        $validated = $request->validate([
            'brand' => ['required', 'string', 'min:1', 'max:120'],
        ]);

        return response()->json([
            'brand' => $medicineService->normalizeMedicineName($validated['brand']),
            'options' => $medicineService->doseOptionsForBrand($validated['brand']),
            // Brand-level frequency / duration / timing — so picking a
            // paediatric strength chip does not leave those cells blank just
            // because that SKU row has no defaults of its own.
            'defaults' => $medicineService->brandDosingDefaults($validated['brand']),
        ]);
    }
}
