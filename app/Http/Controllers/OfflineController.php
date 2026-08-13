<?php

namespace App\Http\Controllers;

use App\Services\OfflineBagService;
use App\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineController extends Controller
{
    public function bag(Request $request, OfflineBagService $bags): JsonResponse
    {
        $user = $request->user();

        if (! $user?->canRecordVisitNotes() || ! $user->belongsToCurrentTenant()) {
            abort(403);
        }

        return response()->json($bags->build($user));
    }

    public function sync(Request $request, OfflineSyncService $sync): JsonResponse
    {
        $user = $request->user();

        if (! $user?->canRecordVisitNotes() || ! $user->belongsToCurrentTenant()) {
            abort(403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'max:'.OfflineSyncService::MAX_ITEMS],
            'items.*.id' => ['required', 'string', 'max:40'],
            'items.*.type' => ['required', 'string', 'in:rx_save,visiting_visit'],
            'items.*.booking_id' => ['nullable', 'string', 'max:40'],
            'items.*.schedule_session_id' => ['nullable'],
            'items.*.patient_name' => ['nullable', 'string', 'max:120'],
            'items.*.patient_phone' => ['nullable', 'string', 'max:20'],
            'items.*.visit_date' => ['nullable', 'date'],
            'items.*.data' => ['nullable', 'array'],
        ]);

        return response()->json([
            'results' => $sync->apply($user, $validated['items']),
        ]);
    }
}
