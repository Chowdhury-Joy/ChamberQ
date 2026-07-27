<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_page_shows_empty_state_when_clinic_has_no_schedules(): void
    {
        $tenant = Tenant::create(['id' => 'empty-book', 'plan_tier' => 'solo', 'name' => 'Empty Clinic']);
        Domain::create(['domain' => 'empty-book.test', 'tenant_id' => 'empty-book']);

        $response = $this->get('http://empty-book.test/book');

        $response->assertStatus(200);
        $response->assertSee('Booking unavailable');
        $response->assertDontSee('Book Your Appointment');
    }
}
