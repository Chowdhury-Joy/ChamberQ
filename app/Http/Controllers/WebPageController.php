<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Chamber;
use App\Models\Department;
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
        $chambers = tenant()?->isSoloDoctor()
            ? collect()
            : Chamber::query()->orderBy('id')->get();
        $sessions = tenant()?->isSoloDoctor()
            ? collect()
            : ScheduleSession::query()
                ->publiclyBookable()
                ->with(['doctor', 'chamber'])
                ->orderBy('session_name')
                ->get();

        $bookingAvailable = ! tenant()?->isSoloDoctor()
            && $chambers->isNotEmpty()
            && $doctors->isNotEmpty()
            && $sessions->isNotEmpty();

        $departments = tenant()?->isSoloDoctor()
            ? collect()
            : Department::published()->ordered()->limit(8)->get();

        $blogPosts = tenant()?->isSoloDoctor()
            ? collect()
            : BlogPost::published()->ordered()->limit(8)->get();

        $websiteDoctors = tenant()?->isSoloDoctor()
            ? collect()
            : Doctor::publishedOnWebsite()->limit(8)->get();

        return view($view, [
            'page' => $page,
            // Loaded here rather than queried from inside section blades.
            'doctors' => $doctors,
            'chambers' => $chambers,
            'sessions' => $sessions,
            'bookingAvailable' => $bookingAvailable,
            'departments' => $departments,
            'blogPosts' => $blogPosts,
            'websiteDoctors' => $websiteDoctors,
        ]);
    }
}
