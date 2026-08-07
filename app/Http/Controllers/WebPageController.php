<?php

namespace App\Http\Controllers;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\WebPage;
use Illuminate\Http\Request;

class WebPageController extends Controller
{
    public function show(Request $request, ?string $slug = null)
    {
        $slug = $slug ? '/' . ltrim($slug, '/') : '/';

        $page = WebPage::where('slug', $slug)
            ->where('is_published', true)
            ->first();
            
        if (!$page && $slug === '/') {
            // Default fallback if tenant hasn't published a home page yet
            return response()->view('tenant.webpage_default', [], 404);
        }
        
        if (!$page) {
            abort(404);
        }

        $view = tenant()?->isSoloDoctor()
            ? 'tenant.solo.webpage'
            : 'tenant.webpage';

        $doctors = Doctor::orderBy('name')->get();
        $sessions = tenant()?->isSoloDoctor()
            ? collect()
            : ScheduleSession::with(['doctor', 'chamber'])->orderBy('session_name')->get();

        $bookingAvailable = ! tenant()?->isSoloDoctor()
            && Chamber::exists()
            && $doctors->isNotEmpty()
            && $sessions->isNotEmpty();

        return view($view, [
            'page' => $page,
            // Loaded here rather than queried from inside section blades.
            'doctors' => $doctors,
            'sessions' => $sessions,
            'bookingAvailable' => $bookingAvailable,
        ]);
    }
}
