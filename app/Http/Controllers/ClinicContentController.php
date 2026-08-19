<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Department;
use App\Models\Doctor;
use App\Support\PublicSeo;
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
            ...$this->layoutData($department->title, [
                'description' => $department->excerpt,
                'image' => $department->image_url,
            ]),
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
            ...$this->layoutData($post->title, [
                'description' => $post->excerpt,
                'image' => $post->image_url,
                'ogType' => 'article',
                'jsonLd' => [PublicSeo::articleGraph($post, tenant())],
            ]),
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
            ...$this->layoutData($doctor->name, [
                'description' => PublicSeo::plain((string) $doctor->bio) ?: $doctor->websiteSpecialtyLabel(),
                'image' => $doctor->photo_url,
                'jsonLd' => [PublicSeo::physicianGraph($doctor, tenant())],
            ]),
        ]);
    }

    private function ensureClinicTenant(): void
    {
        abort_unless(tenant() && ! tenant()->isSoloDoctor(), 404);
    }

    /**
     * @param  array{description?: ?string, image?: ?string, ogType?: string, jsonLd?: list<array<string, mixed>>, index?: bool}  $seo
     * @return array<string, mixed>
     */
    private function layoutData(string $pageTitle, array $seo = []): array
    {
        $tenant = tenant();
        $brand = $tenant?->displayName() ?? 'ChamberQ';

        return [
            'pageTitle' => $pageTitle,
            'tenant' => $tenant,
            'brand' => $brand,
            'themeColor' => $tenant?->cssThemeColor() ?? \App\Models\Tenant::DEFAULT_THEME_COLOR,
            'locale' => app()->getLocale(),
            'banglaHomepage' => $tenant?->hasFeature('bangla_homepage') ?? false,
            'seo' => $tenant
                ? PublicSeo::tenantPage(
                    $tenant,
                    $pageTitle.' | '.$brand,
                    $seo['description'] ?? null,
                    $seo['index'] ?? true,
                    $seo['image'] ?? null,
                    $seo['ogType'] ?? 'website',
                    $seo['jsonLd'] ?? [],
                )
                : [],
        ];
    }
}
