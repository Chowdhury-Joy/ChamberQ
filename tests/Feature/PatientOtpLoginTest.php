<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Models\PatientAccount;
use App\Models\Tenant;
use App\Services\PatientOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class PatientOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sms.enabled' => true, 'sms.driver' => 'log']);
        RateLimiter::clear('patient-otp-send:01710000001');
        RateLimiter::clear('patient-otp-send:01710000002');
        RateLimiter::clear('patient-otp-send:01710000003');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_page_is_public(): void
    {
        $this->get('http://localhost/me/login')
            ->assertOk()
            ->assertSee('Patient login', escape: false);
    }

    public function test_me_redirects_guests_to_login(): void
    {
        $this->get('http://localhost/me')
            ->assertRedirect('/me/login');
    }

    public function test_otp_then_verify_signs_in_without_debiting_a_tenant_wallet(): void
    {
        $tenant = Tenant::create([
            'id' => 'otp-clinic',
            'name' => 'OTP Clinic',
            'plan_tier' => 'solo',
            'sms_balance' => 9,
        ]);

        $sent = null;
        $gateway = Mockery::mock(SmsGateway::class);
        $gateway->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (string $to, string $message) use (&$sent) {
                $sent = $message;
                $this->assertSame('8801710000001', $to);
            });
        $this->app->instance(SmsGateway::class, $gateway);

        $this->post('http://localhost/me/login/otp', [
            'phone' => '01710000001',
        ])->assertRedirect('/me/login');

        $this->assertSame(9, $tenant->fresh()->sms_balance);
        $this->assertSame(0, PatientAccount::query()->count());
        $this->assertNotNull($sent);
        $this->assertMatchesRegularExpression('/\b(\d{6})\b/', $sent);
        preg_match('/\b(\d{6})\b/', $sent, $matches);
        $code = $matches[1];

        $this->post('http://localhost/me/login/verify', [
            'phone' => '01710000001',
            'code' => $code,
        ])->assertRedirect('/me');

        $this->assertAuthenticated('patient');
        $account = PatientAccount::query()->where('phone', '01710000001')->first();
        $this->assertNotNull($account);

        $this->get('http://localhost/me')
            ->assertOk()
            ->assertSee('My serials', escape: false);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $gateway = Mockery::mock(SmsGateway::class);
        $gateway->shouldReceive('send')->once();
        $this->app->instance(SmsGateway::class, $gateway);

        $this->post('http://localhost/me/login/otp', ['phone' => '01710000002'])
            ->assertRedirect('/me/login');

        $this->post('http://localhost/me/login/verify', [
            'phone' => '01710000002',
            'code' => '000000',
        ])->assertSessionHasErrors('code');

        $this->assertGuest('patient');
    }

    public function test_a_fourth_otp_for_the_same_phone_is_rate_limited(): void
    {
        $gateway = Mockery::mock(SmsGateway::class);
        $gateway->shouldReceive('send')->times(PatientOtpService::MAX_SEND_PER_PHONE);
        $this->app->instance(SmsGateway::class, $gateway);

        for ($i = 0; $i < PatientOtpService::MAX_SEND_PER_PHONE; $i++) {
            $this->post('http://localhost/me/login/otp', ['phone' => '01710000003'])
                ->assertRedirect('/me/login');
        }

        $this->post('http://localhost/me/login/otp', ['phone' => '01710000003'])
            ->assertSessionHasErrors('phone');
    }
}
