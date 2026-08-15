<?php

namespace App\Http\Controllers;

use App\Services\PatientOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PatientAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (auth('patient')->check()) {
            return redirect('/me');
        }

        return view('patient.login', [
            'phone' => session('patient_otp_phone'),
            'codeSent' => session('patient_otp_sent', false),
        ]);
    }

    public function sendOtp(Request $request, PatientOtpService $otp): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $otp->send($validated['phone']);
        } catch (Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return back()
                ->withInput()
                ->withErrors(['phone' => __('Could not send the code. Try again.')]);
        }

        return redirect('/me/login')
            ->with('patient_otp_phone', \App\Support\BdPhone::normalize($validated['phone']))
            ->with('patient_otp_sent', true)
            ->with('status', __('We sent a code to :phone', [
                'phone' => \App\Support\BdPhone::normalize($validated['phone']),
            ]));
    }

    public function verify(Request $request, PatientOtpService $otp): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $account = $otp->verify($validated['phone'], $validated['code']);

        auth('patient')->login($account, remember: true);
        $request->session()->regenerate();

        return redirect()->intended('/me');
    }

    public function logout(Request $request): RedirectResponse
    {
        auth('patient')->logout();

        // Invalidate, not just forget-and-rotate-the-token. Regenerating the
        // CSRF token alone leaves the session id the browser arrived with still
        // valid, so signing out on a shared phone — the normal case here — left
        // the previous session usable by whoever held that id.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/find');
    }
}
