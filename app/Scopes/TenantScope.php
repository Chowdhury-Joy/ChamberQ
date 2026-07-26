<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RuntimeException;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! tenancy()->initialized && ! app()->runningInConsole() && app()->bound('request')) {
            $host = request()->getHost();
            if ($host && ! in_array($host, config('tenancy.central_domains', []), true)) {
                $domain = \App\Models\Domain::where('domain', $host)->first();
                if ($domain && $domain->tenant) {
                    tenancy()->initialize($domain->tenant);
                }
            }
        }

        if (tenancy()->initialized) {
            $builder->where($model->getTable() . '.tenant_id', tenant('id'));
            return;
        }

        // Central users (such as super_admin) have tenant_id = null and operate
        // on central domains outside initialized tenancy. Scope central queries
        // for User models to whereNull('tenant_id').
        if ($model instanceof \App\Models\User) {
            $builder->whereNull($model->getTable() . '.tenant_id');
            return;
        }

        // Console commands (seeders, migrations, tinker) and tests legitimately
        // run outside tenancy context. But an HTTP request that reaches a
        // strictly tenant-scoped model without initialized tenancy is a routing
        // or middleware misconfiguration — silently returning an unscoped query
        // would leak every tenant's data.
        if (! app()->runningInConsole()) {
            throw new RuntimeException(
                sprintf(
                    'TenantScope applied to %s outside an initialized tenancy context during an HTTP request. '
                    . 'This is a routing or middleware misconfiguration.',
                    $model->getTable()
                )
            );
        }
    }
}
