<?php

namespace Tests\Feature;

use App\Exceptions\BookingUnavailableException;
use Illuminate\Http\Request;
use Tests\TestCase;

class BookingUnavailableLivewireRenderTest extends TestCase
{
    public function test_a_livewire_desk_call_does_not_redirect_back(): void
    {
        $request = Request::create('http://127.0.0.1/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');
        $request->headers->set('Referer', 'http://127.0.0.1/mups/admin/live-queue-control');

        $response = BookingUnavailableException::dateBlocked()->render($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringNotContainsString('Redirecting', (string) $response->getContent());
    }
}
