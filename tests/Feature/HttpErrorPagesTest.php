<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_page_is_branded(): void
    {
        $this->get('http://localhost/storage/visit-photos/nope.jpg')
            ->assertForbidden()
            ->assertSee('ChamberQ', false)
            ->assertSee('This page isn’t for you', false)
            ->assertDontSee('tracking-wider', false);
    }

    public function test_not_found_page_is_branded(): void
    {
        $this->get('http://localhost/no-such-chamber')
            ->assertNotFound()
            ->assertSee('ChamberQ', false)
            ->assertSee('We can’t find that page', false)
            ->assertDontSee('tracking-wider', false);
    }

    public function test_json_forbidden_stays_json(): void
    {
        $response = $this->getJson('http://localhost/storage/visit-photos/nope.jpg');

        $response->assertForbidden();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('This page isn’t for you', $response->getContent());
    }
}
