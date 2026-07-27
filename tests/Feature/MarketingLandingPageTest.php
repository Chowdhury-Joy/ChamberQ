<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingLandingPageTest extends TestCase
{
    public function test_central_root_shows_solo_focused_sales_landing(): void
    {
        $response = $this->get('http://localhost/');

        $response->assertOk();
        $response->assertSee('Doctor Gemini', escape: false);
        $response->assertSee('Patients wait less. They tell others.', escape: false);
        $response->assertSee('Built for solo doctors', escape: false);
        $response->assertSee('2 hrs', escape: false);
        $response->assertSee('15 min', escape: false);
        $response->assertSee('Before using us', escape: false);
        $response->assertSee('After using us', escape: false);
        $response->assertSee('Phone ringing through consults', escape: false);
        $response->assertSee('Patients recommend you', escape: false);
        $response->assertSee('Your chamber online', escape: false);
        $response->assertSee('Pick a session', escape: false);
        $response->assertSee('Confirm details', escape: false);
        $response->assertSee('Serial ticket', escape: false);
        $response->assertSee('Live queue', escape: false);
        $response->assertSee('Your day list', escape: false);
        $response->assertSee('They tell others', escape: false);
        $response->assertSee('value-time.png', escape: false);
        $response->assertSee('৳5,000', escape: false);
        $response->assertSee('৳2,000', escape: false);
        $response->assertSee('৳25,000', escape: false);
        $response->assertSee('৳7,500', escape: false);
        $response->assertSee('For solo doctors', escape: false);
        $response->assertSee('WhatsApp about Solo', escape: false);
        $response->assertSee('WhatsApp about Clinic', escape: false);
        $response->assertDontSee('Running a multi-doctor clinic?', escape: false);
        $response->assertSee('wa.me/', escape: false);
        $response->assertDontSee('Better reviews', escape: false);
        $response->assertDontSee('images.unsplash.com', escape: false);
        $response->assertDontSee('fonts.googleapis.com', escape: false);
        $response->assertDontSee("Let's get started", escape: false);
        $response->assertDontSee('Ready for calmer days?', escape: false);
    }

    public function test_central_root_whatsapp_links_target_configured_number(): void
    {
        config(['marketing.whatsapp' => '8801712345678']);

        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('wa.me/8801712345678', escape: false);
    }

    public function test_step_placeholders_render_when_images_missing(): void
    {
        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('step-4-serial-ticket.png', escape: false)
            ->assertSee('step-6-doctor-day-list.png', escape: false)
            ->assertSee('value-mouth.png', escape: false);
    }

    public function test_primary_buttons_use_white_text_class(): void
    {
        $css = file_get_contents(public_path('css/marketing.css'));

        $this->assertStringContainsString('.mk-btn-primary', $css);
        $this->assertMatchesRegularExpression('/\.mk-btn-primary\s*\{[^}]*color:\s*#fff/s', $css);
    }
}
