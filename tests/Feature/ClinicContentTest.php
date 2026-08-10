<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Department;
use App\Models\Domain;
use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\WebPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicContentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $clinic;

    private Tenant $solo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Tenant::create(['id' => 'clinic-cms', 'plan_tier' => 'clinic']);
        Domain::create(['domain' => 'clinic-cms.localhost', 'tenant_id' => $this->clinic->id]);

        $this->solo = Tenant::create(['id' => 'solo-cms', 'plan_tier' => 'solo']);
        Domain::create(['domain' => 'solo-cms.localhost', 'tenant_id' => $this->solo->id]);
    }

    public function test_clinic_can_view_published_department_and_blog_pages(): void
    {
        tenancy()->initialize($this->clinic);

        Department::create([
            'title' => 'Cardiology',
            'slug' => 'cardiology',
            'excerpt' => 'Heart care',
            'is_published' => true,
        ]);

        BlogPost::create([
            'title' => 'Stay Active',
            'slug' => 'stay-active',
            'excerpt' => 'Move more',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Doctor::create([
            'name' => 'Dr. Karim',
            'show_on_website' => true,
            'public_slug' => 'dr-karim',
            'public_title' => 'Cardiologist',
        ]);

        tenancy()->end();

        $this->get('http://clinic-cms.localhost/departments')
            ->assertOk()
            ->assertSee('Cardiology');

        $this->get('http://clinic-cms.localhost/departments/cardiology')
            ->assertOk()
            ->assertSee('Heart care');

        $this->get('http://clinic-cms.localhost/blog')
            ->assertOk()
            ->assertSee('Stay Active');

        $this->get('http://clinic-cms.localhost/blog/stay-active')
            ->assertOk()
            ->assertSee('Move more');

        $this->get('http://clinic-cms.localhost/doctors')
            ->assertOk()
            ->assertSee('Dr. Karim');

        $this->get('http://clinic-cms.localhost/doctors/dr-karim')
            ->assertOk()
            ->assertSee('Cardiologist');
    }

    public function test_solo_tenant_gets_404_on_clinic_content_routes(): void
    {
        $this->get('http://solo-cms.localhost/departments')->assertNotFound();
        $this->get('http://solo-cms.localhost/blog')->assertNotFound();
        $this->get('http://solo-cms.localhost/doctors')->assertNotFound();
    }

    public function test_unpublished_department_is_hidden(): void
    {
        tenancy()->initialize($this->clinic);

        Department::create([
            'title' => 'Secret Wing',
            'slug' => 'secret-wing',
            'is_published' => false,
        ]);

        tenancy()->end();

        $this->get('http://clinic-cms.localhost/departments/secret-wing')->assertNotFound();
    }

    public function test_homepage_section_renders_department_from_database(): void
    {
        tenancy()->initialize($this->clinic);

        Department::create([
            'title' => 'Physio Rehab',
            'slug' => 'physio-rehab',
            'excerpt' => 'Get moving again',
            'is_published' => true,
        ]);

        WebPage::create([
            'title' => 'Home',
            'slug' => '/',
            'is_published' => true,
            'content' => [
                [
                    'type' => 'service_matrix',
                    'data' => [
                        'heading' => 'Our services',
                    ],
                ],
            ],
        ]);

        tenancy()->end();

        $this->get('http://clinic-cms.localhost/')
            ->assertOk()
            ->assertSee('Physio Rehab')
            ->assertSee('Get moving again');
    }
}
