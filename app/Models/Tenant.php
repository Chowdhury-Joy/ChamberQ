<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant
{
    use HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'template_id',
            'layout_id',
            'custom_code',
            'custom_code_approved_at',
            'billing_status',
            'plan_tier',
            'slot_cap_mode',
            'feature_flags',
            'created_at',
            'updated_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'feature_flags' => 'array',
            'custom_code_approved_at' => 'datetime',
        ];
    }
}
