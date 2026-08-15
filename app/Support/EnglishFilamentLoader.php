<?php

namespace App\Support;

use Illuminate\Contracts\Translation\Loader;

/**
 * Filament's own sidebar / topbar / buttons ship a Bangla pack. Chamber
 * language is for reading copy on the desk, not for those controls.
 */
final class EnglishFilamentLoader implements Loader
{
    public function __construct(private Loader $inner) {}

    public function load($locale, $group, $namespace = null): array
    {
        if (is_string($namespace) && str_starts_with($namespace, 'filament') && $locale === 'bn') {
            $locale = 'en';
        }

        return $this->inner->load($locale, $group, $namespace);
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return $this->inner->namespaces();
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->inner->{$method}(...$arguments);
    }
}
