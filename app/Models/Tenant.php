<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant
{
    use HasDomains;

    public static function getCustomColumns(): array
    {
        // Every real column MUST be listed here. Anything omitted is folded into
        // the `data` JSON blob by stancl's VirtualColumn: PHP attribute reads
        // appear to work while SQL filters silently match nothing.
        return [
            'id',
            'name',
            'contact_phone',
            'whatsapp_number',
            'theme_color',
            'default_locale',
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

    /** The name patients see. Falls back to the subdomain rather than showing nothing. */
    public function displayName(): string
    {
        return filled($this->name) ? $this->name : (string) $this->id;
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
