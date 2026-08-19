<?php

namespace App\Support;

use App\Models\Condition;
use App\Models\Medicine;
use Illuminate\Support\Facades\Schema;

/**
 * The configuration this app must not go live without.
 *
 * Every item here is something that has no code fix — it is a value someone has
 * to set on the server, and every one of them fails *quietly*. Debug mode shows
 * strangers a stack trace. `MAIL_MAILER=log` means a locked-out doctor's
 * password reset goes to a file nobody reads. `SMS_DRIVER=log` means patients
 * are never told their serial. None of these throw, so nothing surfaces them
 * until a real person is affected.
 *
 * The lesson this codebase keeps relearning (twice in `bug_history.md` alone)
 * is that a rule people must remember is a rule that breaks. So this is a list
 * a machine checks, not a page in a runbook — see `app:production-check`, which
 * CI and the deploy step can both run.
 */
class ProductionReadiness
{
    public const SEVERITY_BLOCKER = 'blocker';

    public const SEVERITY_WARNING = 'warning';

    /**
     * @return list<array{severity: string, key: string, problem: string, fix: string}>
     */
    public static function problems(): array
    {
        $problems = [];

        foreach (self::checks() as $check) {
            $found = ($check['detect'])();

            if ($found !== null) {
                $problems[] = [
                    'severity' => $check['severity'],
                    'key' => $check['key'],
                    'problem' => $found,
                    'fix' => $check['fix'],
                ];
            }
        }

        return $problems;
    }

    /**
     * @return list<array{severity: string, key: string, fix: string, detect: callable(): ?string}>
     */
    private static function checks(): array
    {
        return [
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'APP_DEBUG',
                'fix' => 'APP_DEBUG=false',
                'detect' => fn (): ?string => config('app.debug')
                    ? 'Debug mode is on — stack traces, env values and queries are shown to anyone who triggers an error.'
                    : null,
            ],
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'APP_ENV',
                'fix' => 'APP_ENV=production',
                'detect' => fn (): ?string => config('app.env') !== 'production'
                    ? 'APP_ENV is "'.config('app.env').'", so Laravel is not applying production behaviour.'
                    : null,
            ],
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'APP_KEY',
                'fix' => 'php artisan key:generate',
                'detect' => fn (): ?string => blank(config('app.key'))
                    ? 'No APP_KEY — sessions and encrypted values cannot be trusted.'
                    : null,
            ],
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'DB_CONNECTION',
                'fix' => 'DB_CONNECTION=mysql (validated 2026-08-08; see architecture.md)',
                'detect' => fn (): ?string => config('database.default') === 'sqlite'
                    ? 'Running on SQLite. Production is MySQL, and the two differ in ways this project has already been bitten by (foreign-key ordering, ONLY_FULL_GROUP_BY, date columns, row locking).'
                    : null,
            ],
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'APP_URL',
                'fix' => 'APP_URL=https://your-domain',
                'detect' => function (): ?string {
                    $url = (string) config('app.url');

                    if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
                        return 'APP_URL is "'.$url.'". Patient ticket links in SMS are built from this, so they would point at the server itself.';
                    }

                    return str_starts_with($url, 'https://')
                        ? null
                        : 'APP_URL is not https. Patient and clinical pages would be served in the clear.';
                },
            ],
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'MAIL_MAILER',
                'fix' => 'Configure a real MAIL_* transport',
                'detect' => fn (): ?string => in_array(config('mail.default'), ['log', 'array'], true)
                    ? 'Mail goes to "'.config('mail.default').'". Password resets would never reach a locked-out doctor.'
                    : null,
            ],
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'SMS_DRIVER',
                'fix' => 'SMS_DRIVER=http with real gateway credentials, or SMS_ENABLED=false to disable deliberately',
                'detect' => function (): ?string {
                    if (! config('sms.enabled')) {
                        return null; // Off on purpose is a valid production state.
                    }

                    return config('sms.driver') === 'log'
                        ? 'SMS is enabled but the driver is "log" — booking confirmations are written to a file, not sent. Patients would never receive a serial.'
                        : null;
                },
            ],
            [
                'severity' => self::SEVERITY_WARNING,
                'key' => 'FILESYSTEM_DISK',
                'fix' => 'Point clinical media at durable storage, or confirm the server disk is backed up',
                'detect' => fn (): ?string => config('filesystems.default') === 'local'
                    ? 'Clinical media (voice notes, prescription photos) is on the server\'s own disk. If that disk is lost the records are gone. VisitMediaService is disk-agnostic, so this is a config switch once storage is chosen.'
                    : null,
            ],
            [
                'severity' => self::SEVERITY_WARNING,
                'key' => 'SESSION_SECURE_COOKIE',
                'fix' => 'SESSION_SECURE_COOKIE=true',
                'detect' => fn (): ?string => config('session.secure') !== true
                    ? 'Session cookie is not marked secure, so it can be sent over plain http.'
                    : null,
            ],
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'MEDICINE_CATALOGUE',
                'fix' => 'php artisan catalogues:load (or medicines:load) after every migrate on a new server',
                'detect' => function (): ?string {
                    if (! app()->environment('production')) {
                        return null;
                    }

                    if (! Schema::hasTable('medicines')) {
                        return null;
                    }

                    return Medicine::query()->count() === 0
                        ? 'The medicine catalogue is empty. Prescription pickers will show no brands until `medicines:load` is run — migrations alone do not import the CSV.'
                        : null;
                },
            ],
            [
                'severity' => self::SEVERITY_BLOCKER,
                'key' => 'CONDITION_CATALOGUE',
                'fix' => 'php artisan catalogues:load (or conditions:load) after every migrate on a new server',
                'detect' => function (): ?string {
                    if (! app()->environment('production')) {
                        return null;
                    }

                    if (! Schema::hasTable('conditions')) {
                        return null;
                    }

                    return Condition::query()->count() === 0
                        ? 'The diagnosis catalogue is empty. Every diagnosis will be recorded as uncoded free text — no coded history, no diagnosis packs, no advice presets — until `conditions:load` is run.'
                        : null;
                },
            ],
            [
                'severity' => self::SEVERITY_WARNING,
                'key' => 'TRUSTED_PROXIES',
                'fix' => 'TRUSTED_PROXIES=your load-balancer IP or CIDR (not *)',
                'detect' => fn (): ?string => config('app.trusted_proxies') === '*'
                    ? 'TRUSTED_PROXIES is "*", so any client can spoof X-Forwarded-For and bypass IP throttles. Set the real reverse-proxy IPs unless PHP is unreachable except through that proxy.'
                    : null,
            ],
            [
                'severity' => self::SEVERITY_WARNING,
                'key' => 'VAPID',
                'fix' => 'Generate a key pair with php -r \'require "vendor/autoload.php"; echo json_encode(Minishlink\\WebPush\\VAPID::createVapidKeys()), PHP_EOL;\' and set VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY',
                'detect' => function (): ?string {
                    $public = (string) config('webpush.vapid.public_key');
                    $private = (string) config('webpush.vapid.private_key');

                    if ($public !== '' && $private !== '') {
                        return null;
                    }

                    return 'VAPID keys are empty. Pocket buzz on a locked phone will not fire (the ticket can still vibrate while it is open). Open-tab alerts do not need these keys.';
                },
            ],
        ];
    }
}
