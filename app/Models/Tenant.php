<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant
{
    use HasDomains;

    public const DEFAULT_THEME_COLOR = '#2563eb';

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
            'logo_url',
            'favicon_url',
            'font_family',
            'tagline',
            'default_locale',
            'template_id',
            'layout_id',
            'custom_code',
            'custom_code_approved_at',
            'billing_status',
            'plan_tier',
            'slot_cap_mode',
            'feature_flags',
            'call_timeout_seconds',
            'estimated_time_buffer_minutes',
            'first_n_patients',
            'first_n_arrival_offset_minutes',
            'call_audio_preset',
            'call_audio_path',
            'created_at',
            'updated_at',
        ];
    }

    /** The name patients see. Falls back to the subdomain rather than showing nothing. */
    public function displayName(): string
    {
        return filled($this->name) ? $this->name : (string) $this->id;
    }

    /**
     * Public URL for the waiting-room call chime.
     * Relative paths keep tenant domains (e.g. solo.localhost) working.
     */
    public function callAudioUrl(): string
    {
        $preset = $this->call_audio_preset ?? 'chime';

        if ($preset === 'custom' && filled($this->call_audio_path)) {
            return '/storage/'.ltrim((string) $this->call_audio_path, '/');
        }

        return match ($preset) {
            'soft-bell' => '/audio/soft-bell.wav',
            'alert' => '/audio/alert.wav',
            default => '/audio/chime.wav',
        };
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
            // Filament KeyValue stores string "true"/"false"; (bool)"false" === true.
            return filter_var($flags[$feature], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Fall back to tier defaults
        return match ($this->plan_tier) {
            'solo' => match ($feature) {
                'lab_tests' => false,
                'multiple_chambers' => false,
                'multiple_doctors' => false,
                'bangla_homepage' => false,
                default => false,
            },
            'clinic' => match ($feature) {
                'lab_tests' => true,
                'multiple_chambers' => true,
                'multiple_doctors' => true,
                'bangla_homepage' => false,
                default => false,
            },
            default => false,
        };
    }

    public function isClinic(): bool
    {
        return $this->plan_tier === 'clinic';
    }

    public function isSoloDoctor(): bool
    {
        return ! $this->isClinic();
    }
}
