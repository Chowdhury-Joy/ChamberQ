<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
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

        return view('tenant.webpage', [
            'page' => $page,
            // Loaded here rather than queried from inside the template.
            'doctors' => Doctor::orderBy('name')->get(),
        ]);
    }
}
