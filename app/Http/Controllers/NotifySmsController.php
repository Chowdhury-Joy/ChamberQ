<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Prescription;
use App\Models\User;
use App\Services\SmsService;
use App\Support\StaffDeskScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifySmsController extends Controller
{
    /**
     * Staff-tapped cancellation SMS (vacation block or end-session).
     */
    public function cancellation(Request $request, Booking $booking, SmsService $sms): JsonResponse
    {
        $user = $request->user();
        $this->authorizeStaffNotify($user);
        StaffDeskScope::assertCanAccessBooking($user, $booking);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $custom = trim((string) ($validated['message'] ?? ''));
        if ($custom !== '' && preg_match('/https?:\/\//i', $custom)) {
            abort(422, __('Custom cancellation text cannot include links.'));
        }
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

        StaffDeskScope::assertCanAccessBooking($request->user(), $booking);

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
