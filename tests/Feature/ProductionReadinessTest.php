<?php

namespace Tests\Feature;

use App\Support\ProductionReadiness;
use Tests\TestCase;

/**
 * Every item `ProductionReadiness` checks is a value someone sets on a server,
 * and every one of them fails silently — debug mode shows strangers a stack
 * trace, `MAIL_MAILER=log` swallows a locked-out doctor's password reset,
 * `SMS_DRIVER=log` means no patient is ever told their serial.
 *
 * A check that cannot fail is decoration, and a check that cannot pass gets
 * switched off. Both directions are asserted here.
 */
class ProductionReadinessTest extends TestCase
{
    /** @return array<string, mixed> A configuration a real deployment would have. */
    private function productionConfig(): array
    {
        return [
            'app.debug' => false,
            'app.env' => 'production',
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.url' => 'https://chamberq.example',
            'database.default' => 'mysql',
            'mail.default' => 'smtp',
            'sms.enabled' => true,
            'sms.driver' => 'http',
            'filesystems.default' => 's3',
            'session.secure' => true,
        ];
    }

    public function test_a_correctly_configured_environment_reports_nothing(): void
    {
        config($this->productionConfig());

        $this->assertSame([], ProductionReadiness::problems());
    }

    /**
     * @dataProvider unsafeConfigurations
     */
    public function test_each_unsafe_setting_is_caught(string $key, array $override, string $severity): void
    {
        config(array_merge($this->productionConfig(), $override));

        $keys = array_column(ProductionReadiness::problems(), 'key');

        $this->assertContains($key, $keys, "{$key} was misconfigured but nothing reported it.");

        $problem = collect(ProductionReadiness::problems())->firstWhere('key', $key);

        $this->assertSame($severity, $problem['severity']);
        $this->assertNotEmpty($problem['problem'], 'A problem must say what is actually wrong.');
        $this->assertNotEmpty($problem['fix'], 'A problem must say how to fix it.');
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string}> */
    public static function unsafeConfigurations(): array
    {
        $blocker = ProductionReadiness::SEVERITY_BLOCKER;
        $warning = ProductionReadiness::SEVERITY_WARNING;

        return [
            'debug mode on' => ['APP_DEBUG', ['app.debug' => true], $blocker],
            'not production env' => ['APP_ENV', ['app.env' => 'local'], $blocker],
            'missing app key' => ['APP_KEY', ['app.key' => ''], $blocker],
            'still on sqlite' => ['DB_CONNECTION', ['database.default' => 'sqlite'], $blocker],
            'url is localhost' => ['APP_URL', ['app.url' => 'http://localhost'], $blocker],
            'url is not https' => ['APP_URL', ['app.url' => 'http://chamberq.example'], $blocker],
            'mail goes to a log' => ['MAIL_MAILER', ['mail.default' => 'log'], $blocker],
            'sms goes to a log' => ['SMS_DRIVER', ['sms.driver' => 'log'], $blocker],
            'media on the server disk' => ['FILESYSTEM_DISK', ['filesystems.default' => 'local'], $warning],
            'insecure session cookie' => ['SESSION_SECURE_COOKIE', ['session.secure' => false], $warning],
        ];
    }

    /** Switching SMS off deliberately is a valid production state, not a fault. */
    public function test_sms_disabled_on_purpose_is_not_reported(): void
    {
        config(array_merge($this->productionConfig(), [
            'sms.enabled' => false,
            'sms.driver' => 'log',
        ]));

        $this->assertNotContains('SMS_DRIVER', array_column(ProductionReadiness::problems(), 'key'));
    }

    public function test_the_command_fails_the_deploy_when_a_blocker_is_present(): void
    {
        config(array_merge($this->productionConfig(), ['app.debug' => true]));

        $this->artisan('app:production-check')
            ->expectsOutputToContain('APP_DEBUG')
            ->assertExitCode(1);
    }

    public function test_the_command_passes_a_ready_environment(): void
    {
        config($this->productionConfig());

        $this->artisan('app:production-check --strict')->assertExitCode(0);
    }

    /** Warnings alone do not block a release unless the deploy asks them to. */
    public function test_warnings_alone_pass_normally_but_fail_under_strict(): void
    {
        config(array_merge($this->productionConfig(), ['filesystems.default' => 'local']));

        $this->artisan('app:production-check')->assertExitCode(0);
        $this->artisan('app:production-check --strict')->assertExitCode(1);
    }
}
