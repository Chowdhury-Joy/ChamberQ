<?php

namespace Tests\Feature;

use App\Filament\TenantAdmin\Resources\BlogPosts\BlogPostResource;
use App\Filament\TenantAdmin\Resources\Concerns\ClinicWebsiteResource;
use App\Filament\TenantAdmin\Resources\Departments\DepartmentResource;
use ReflectionClass;
use Tests\TestCase;

class ClinicWebsiteNavigationTest extends TestCase
{
    public function test_clinic_website_resources_group_under_website_via_method(): void
    {
        $this->assertSame('Website', DepartmentResource::getNavigationGroup());
        $this->assertSame('Website', BlogPostResource::getNavigationGroup());

        $trait = new ReflectionClass(ClinicWebsiteResource::class);
        $this->assertTrue($trait->hasMethod('getNavigationGroup'));
        $this->assertFalse($trait->hasProperty('navigationGroup'));
    }
}
