<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards that catch a class of mistake no runtime check can.
 *
 * `ProductionReadiness` deliberately only covers values someone sets on a
 * server, so these do not belong there — they are code defects, and the place
 * they are cheapest to catch is CI on the pull request that introduces them.
 */
class SourceHygieneTest extends TestCase
{
    /**
     * Directories that ship to the server.
     *
     * @var list<string>
     */
    private const SOURCE_DIRECTORIES = [
        'app',
        'bootstrap',
        'config',
        'routes',
        'database',
        'resources',
    ];

    /**
     * No absolute developer path may reach the server.
     *
     * Five files once carried `file_put_contents('/Users/<someone>/…')` left
     * behind by diagnostic scaffolding — on the marketing homepage, the
     * language switcher, the Filament navigation build and every thrown
     * exception. On a Linux host that path does not exist, so it was a PHP
     * warning per request on the busiest public page; had it existed, it was an
     * unrotated log growing forever. Nothing failed loudly, which is exactly
     * why it survived being committed.
     */
    public function test_no_absolute_developer_paths_in_shipped_source(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match('#[\'"](/Users/|/home/[a-z])#i', $contents)) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'Absolute developer paths found in shipped source:',
            ...$offenders,
            'These do not exist on the server. Use base_path()/storage_path(), or delete the diagnostic.',
        ]));
    }

    /**
     * `env()` must not be read outside `config/`.
     *
     * A deployment runs `php artisan config:cache`, after which Laravel stops
     * loading `.env` altogether and `env()` returns null everywhere else. The
     * sign-out diagnostics were gated this way and could therefore never be
     * switched on in production — the one environment they existed for.
     */
    public function test_env_is_only_read_from_config_files(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $file) {
            if (str_starts_with($file, base_path('config'))) {
                continue;
            }

            // PHP only, and not Blade: CSS has its own unrelated `env()`
            // (`env(safe-area-inset-bottom)`, which these views legitimately use
            // for the iPhone home indicator), and it lives inside templates.
            if (! str_ends_with($file, '.php') || str_ends_with($file, '.blade.php')) {
                continue;
            }

            foreach (file($file) ?: [] as $number => $line) {
                // `$this->app->environment(...)` and `app()->environment()` are
                // a different function and are fine.
                if (preg_match('/(?<![>\w])env\s*\(/', $line) && ! str_contains($line, '//')) {
                    $offenders[] = str_replace(base_path().'/', '', $file).':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'env() read outside config/ — returns null once config is cached:',
            ...$offenders,
            'Add a config entry and read it with config().',
        ]));
    }

    /**
     * A missing AUTH_PASSWORD_TIMEOUT must not fall back to Laravel's 3-hour
     * confirm-password window. Session lifetime already ships a one-year
     * default in config/session.php for the same reason.
     */
    public function test_password_confirmation_timeout_default_is_one_year(): void
    {
        $source = (string) file_get_contents(config_path('auth.php'));

        $this->assertMatchesRegularExpression(
            "/env\\('AUTH_PASSWORD_TIMEOUT',\\s*31536000\\)/",
            $source,
            'config/auth.php must default AUTH_PASSWORD_TIMEOUT to one year, not 10800',
        );
        $this->assertDoesNotMatchRegularExpression(
            "/env\\('AUTH_PASSWORD_TIMEOUT',\\s*10800\\)/",
            $source,
        );
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (self::SOURCE_DIRECTORIES as $directory) {
            $path = base_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'css'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
