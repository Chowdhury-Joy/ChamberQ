<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Prescription;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifySmsController extends Controller
{
    /**
     * Staff-tapped cancellation SMS (vacation block or end-session).
     */
    public function cancellation(Request $request, Booking $booking, SmsService $sms): JsonResponse
    {
        $this->authorizeStaffNotify($request->user());

        $custom = $request->string('message')->trim()->toString();
        $message = $sms->sendCancellationNotice(
            $booking,
            $custom !== '' ? $custom : null,
        );

        return response()->json([
            'status' => $message?->status,
            'credits' => $message?->credits ?? 0,
        ]);
    }

    /**
     * Staff-tapped prescription share SMS (48h signed link).
     */
    public function prescription(Request $request, Prescription $prescription, SmsService $sms): JsonResponse
    {
        $this->authorizeStaffNotify($request->user());

        $prescription->loadMissing(['visitRecord.booking']);
        $booking = $prescription->visitRecord?->booking;

        if (! $booking) {
            abort(404);
        }

        $message = $sms->sendPrescriptionNotice($booking, $prescription);

        return response()->json([
            'status' => $message?->status,
            'credits' => $message?->credits ?? 0,
        ]);
    }

    private function authorizeStaffNotify(?User $user): void
    {
        // Role AND practice — these routes have no Filament panel guard.
        if (
            ! $user
            || ! $user->belongsToCurrentTenant()
            || ! (
                $user->canManageOps()
                || $user->canManageQueue()
                || $user->canViewVisitNotes()
            )
        ) {
            abort(403);
        }
    }
}
