<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Department;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicContentController extends Controller
{
    public function departmentsIndex(): View
    {
        $this->ensureClinicTenant();

        return view('tenant.clinic.departments.index', [
            'departments' => Department::published()->ordered()->get(),
            ...$this->layoutData(__('Departments')),
        ]);
    }

    public function departmentShow(string $slug): View
    {
        $this->ensureClinicTenant();

        $department = Department::published()->where('slug', $slug)->firstOrFail();

        return view('tenant.clinic.departments.show', [
            'department' => $department,
            ...$this->layoutData($department->title),
        ]);
    }

    public function blogIndex(): View
    {
        $this->ensureClinicTenant();

        return view('tenant.clinic.blog.index', [
            'posts' => BlogPost::published()->ordered()->get(),
            ...$this->layoutData(__('Health tips')),
        ]);
    }

    public function blogShow(string $slug): View
    {
        $this->ensureClinicTenant();

        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        return view('tenant.clinic.blog.show', [
            'post' => $post,
            ...$this->layoutData($post->title),
        ]);
    }

    public function doctorsIndex(): View
    {
        $this->ensureClinicTenant();

        return view('tenant.clinic.doctors.index', [
            'doctors' => Doctor::publishedOnWebsite()->get(),
            ...$this->layoutData(__('Our doctors')),
        ]);
    }

    public function doctorShow(string $slug): View
    {
        $this->ensureClinicTenant();

        $doctor = Doctor::publishedOnWebsite()->where('public_slug', $slug)->firstOrFail();

        return view('tenant.clinic.doctors.show', [
            'doctor' => $doctor,
            ...$this->layoutData($doctor->name),
        ]);
    }

    private function ensureClinicTenant(): void
    {
        abort_unless(tenant() && ! tenant()->isSoloDoctor(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutData(string $pageTitle): array
    {
        $tenant = tenant();

        return [
            'pageTitle' => $pageTitle,
            'tenant' => $tenant,
            'brand' => $tenant?->displayName() ?? 'ChamberQ',
            'themeColor' => $tenant?->theme_color ?: '#1B2978',
            'locale' => app()->getLocale(),
            'banglaHomepage' => $tenant?->hasFeature('bangla_homepage') ?? false,
        ];
    }
}
