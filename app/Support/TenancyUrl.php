<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class TenancyUrl
{
    /**
     * Route-name prefix used for central-domain path tenancy routes.
     */
    public const PATH_ROUTE_PREFIX = 'path.';

    /**
     * First URL segments reserved on the central domain (marketing, super admin, assets).
     *
     * @return list<string>
     */
    public static function reservedPathPrefixes(): array
    {
        return config('tenancy.reserved_path_prefixes', []);
    }

    /**
     * Regex (without delimiters) for the `{tenant}` path parameter on central domains.
     */
    public static function tenantSlugPattern(): string
    {
        $reserved = implode('|', array_map(
            static fn (string $segment): string => preg_quote($segment, '/'),
            static::reservedPathPrefixes()
        ));

        return '(?!'.$reserved.'$)[a-z0-9\-]+';
    }

    public static function usesPathPrefix(): bool
    {
        if (! tenancy()->initialized || ! app()->bound('request')) {
            return false;
        }

        return in_array(request()->getHost(), config('tenancy.central_domains', []), true);
    }

    public static function pathPrefix(): string
    {
        if (! static::usesPathPrefix()) {
            return '';
        }

        return '/'.tenant('id');
    }

    public static function url(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            $prefix = static::pathPrefix();

            return $prefix !== '' ? $prefix.'/' : '/';
        }

        return static::pathPrefix().$path;
    }

    public static function routeName(string $name): string
    {
        return static::usesPathPrefix() ? self::PATH_ROUTE_PREFIX.$name : $name;
    }

    /**
     * @param  array<string, mixed>|object  $parameters
     */
    public static function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        if (! static::usesPathPrefix()) {
            return route($name, $parameters, $absolute);
        }

        $routeName = static::PATH_ROUTE_PREFIX.$name;

        if (! is_array($parameters)) {
            $parameters = static::normalizeRouteParameters($routeName, $parameters);
        }

        return route($routeName, array_merge(['tenant' => tenant('id')], $parameters), $absolute);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeRouteParameters(string $routeName, mixed $parameters): array
    {
        if ($parameters instanceof Model) {
            $route = app('router')->getRoutes()->getByName($routeName);

            if ($route) {
                $bindingName = collect($route->parameterNames())
                    ->reject(fn (string $parameter): bool => $parameter === 'tenant')
                    ->first();

                if ($bindingName) {
                    return [$bindingName => $parameters];
                }
            }
        }

        return is_array($parameters) ? $parameters : [$parameters];
    }
}
