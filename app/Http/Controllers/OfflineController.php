<?php

namespace App\Http\Controllers;

use App\Models\ScheduleSession;
use App\Services\OfflineBagService;
use App\Services\OfflineQueueBagService;
use App\Services\OfflineSyncService;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
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

    public function queue(Request $request, ScheduleSession $session, OfflineQueueBagService $queues): JsonResponse
    {
        $user = $request->user();

        if (! $user?->canOperateQueueControls() || ! $user->belongsToCurrentTenant()) {
            abort(403);
        }

        if (! StaffDeskJobs::canRunQueue($user)) {
            abort(403);
        }

        StaffDeskScope::assertCanAccessSession($user, $session);

        $date = $request->query('date');

        return response()->json($queues->build($session, is_string($date) ? $date : null));
    }

    public function sync(Request $request, OfflineSyncService $sync): JsonResponse
    {
        $user = $request->user();

        if (! $user?->belongsToCurrentTenant()) {
            abort(403);
        }

        $canRx = $user->canRecordVisitNotes();
        $canQueue = StaffDeskJobs::canRunQueue($user);

        if (! $canRx && ! $canQueue) {
            abort(403);
        }

        $queueTypes = implode(',', [
            OfflineSyncService::TYPE_QUEUE_CALL_NEXT,
            OfflineSyncService::TYPE_QUEUE_PATIENT_ARRIVED,
            OfflineSyncService::TYPE_QUEUE_SKIP,
            OfflineSyncService::TYPE_QUEUE_COMPLETE_WITHOUT_ADVANCE,
        ]);

        $rxTypes = implode(',', [
            OfflineSyncService::TYPE_RX_SAVE,
            OfflineSyncService::TYPE_VISITING_VISIT,
        ]);

        $allowedTypes = $canRx && $canQueue
            ? $rxTypes.','.$queueTypes
            : ($canQueue ? $queueTypes : $rxTypes);

        $validated = $request->validate([
            'items' => ['required', 'array', 'max:'.OfflineSyncService::MAX_ITEMS],
            'items.*.id' => ['required', 'string', 'max:40'],
            'items.*.type' => ['required', 'string', 'in:'.$allowedTypes],
            'items.*.booking_id' => ['nullable', 'string', 'max:40'],
            'items.*.schedule_session_id' => ['nullable'],
            // Deliberately NOT `exists:live_sessions,id` — that rule ignores the
            // tenant scope, so it answered "yes, that id exists" for another
            // chamber's session and turned a validation message into an
            // existence oracle. OfflineSyncService resolves the id through the
            // scope and rejects anything that does not belong here.
            'items.*.live_session_id' => ['nullable', 'integer', 'min:1'],
            'items.*.expected_current_booking_id' => ['nullable', 'string', 'max:40'],
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
