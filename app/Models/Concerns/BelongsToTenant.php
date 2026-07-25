<?php

namespace App\Models\Concerns;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use RuntimeException;

#[ScopedBy([TenantScope::class])]
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            // Under tenancy the current tenant ALWAYS wins. Merely defaulting
            // when tenant_id is empty lets a tenant admin write into another
            // tenant by supplying an id (via a form field or a fillable payload).
            // Outside tenancy — console, seeders, super-admin onboarding — an
            // explicit tenant_id is trusted, because the actor already is.
            if (tenancy()->initialized) {
                $model->tenant_id = tenant('id');
            }
        });

        // A record must never change hands, in any context.
        static::updating(function ($model) {
            if ($model->isDirty('tenant_id')) {
                throw new RuntimeException(sprintf(
                    'The tenant_id of an existing %s cannot be changed.',
                    static::class
                ));
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
