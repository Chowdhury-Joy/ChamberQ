<?php

namespace Tests\Unit;

use App\Support\SafeUrl;
use PHPUnit\Framework\TestCase;

class SafeUrlTest extends TestCase
{
    public function test_rejects_dangerous_schemes(): void
    {
        $this->assertSame('#', SafeUrl::href('javascript:alert(1)'));
        $this->assertSame('#', SafeUrl::href('JaVaScRiPt:alert(1)'));
        $this->assertSame('#', SafeUrl::href('data:text/html,hi'));
        $this->assertSame('#', SafeUrl::href('vbscript:msgbox(1)'));
        $this->assertSame('#', SafeUrl::href('//evil.example'));
    }

    public function test_allows_relative_http_mailto_tel(): void
    {
        $this->assertSame('/book', SafeUrl::href('/book'));
        $this->assertSame('#top', SafeUrl::href('#top'));
        $this->assertSame('https://example.com', SafeUrl::href('https://example.com'));
        $this->assertSame('http://example.com', SafeUrl::href('http://example.com'));
        $this->assertSame('mailto:a@b.com', SafeUrl::href('mailto:a@b.com'));
        $this->assertSame('tel:01712345678', SafeUrl::href('tel:01712345678'));
    }
}
