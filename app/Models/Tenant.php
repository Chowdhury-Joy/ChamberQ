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

    public function hasFeature(string $feature): bool
    {
        // Check feature_flags JSON column first
        $flags = $this->feature_flags ?? [];
        if (array_key_exists($feature, $flags)) {
            return (bool) $flags[$feature];
        }
        
        // Fall back to tier defaults
        return match ($this->plan_tier) {
            'solo' => match ($feature) {
                'lab_tests' => false,
                'multiple_chambers' => false,
                'multiple_doctors' => false,
                default => false,
            },
            'clinic' => match ($feature) {
                'lab_tests' => true,
                'multiple_chambers' => true,
                'multiple_doctors' => true,
                default => false,
            },
            default => false,
        };
    }
}
