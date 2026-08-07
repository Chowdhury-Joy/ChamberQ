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

        if (! $user?->canRecordVisitNotes()) {
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
}
