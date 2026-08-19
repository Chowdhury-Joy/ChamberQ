<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bangla coverage for everything a *patient* can reach.
 *
 * The language switcher offers Bangla on every public page, so a patient can
 * book in Bangla, get a Bangla ticket, then open the portal and hit English —
 * which is what used to happen (15 of the portal's 18 strings were missing).
 * Daily desk chrome (sidebar, Live Queue, roster, dashboard) is covered by
 * StaffDeskBanglaTest. Branding / page-builder form labels are a later pass.
 */
class PatientFacingBanglaTest extends TestCase
{
    /** Views a patient can actually land on. */
    private const PATIENT_FACING_VIEWS = [
        'resources/views/tenant/partials/booking-wizard.blade.php',
        'resources/views/tenant/partials/ticket-body.blade.php',
        'resources/views/tenant/portal/index.blade.php',
        'resources/views/tenant/solo/portal/index.blade.php',
        'resources/views/tenant/partials/portal-prescription-lock.blade.php',
        'resources/views/tenant/screen.blade.php',
        // The combined chamber TV is patient-facing too. It shipped with
        // untranslated strings because only the single-room screen was listed.
        'resources/views/tenant/screen-chamber.blade.php',
        'resources/views/patient/find.blade.php',
        'resources/views/patient/login.blade.php',
        'resources/views/patient/serials.blade.php',
        'resources/views/patient/history.blade.php',
        'resources/views/components/patient/layout.blade.php',
        'resources/views/tenant/prescriptions/share.blade.php',
        'resources/views/tenant/prescriptions/partials/sheet.blade.php',
        'resources/views/tenant/partials/clinic-header.blade.php',
        'resources/views/tenant/clinic/layout.blade.php',
        'resources/views/tenant/webpage.blade.php',
        'resources/views/tenant/sections/hero.blade.php',
        'app/helpers.php',
        'app/Support/PublicSeo.php',
    ];

    public function test_every_patient_facing_string_has_a_bangla_translation(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/bn.json')), true);

        $this->assertIsArray($translations, 'lang/bn.json is not valid JSON.');

        $missing = [];

        foreach (self::PATIENT_FACING_VIEWS as $view) {
            $contents = file_get_contents(base_path($view));

            preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $contents, $matches);

            foreach ($matches[1] as $key) {
                $key = str_replace(["\\'", '\\\\'], ["'", '\\'], $key);

                if (! array_key_exists($key, $translations)) {
                    $missing[] = basename($view).': '.$key;
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missing)),
            "Patient-facing strings with no Bangla translation:\n  ".implode("\n  ", array_unique($missing))
        );
    }
}
