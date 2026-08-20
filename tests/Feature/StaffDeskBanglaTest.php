<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bangla coverage for desk *reading* copy: Live Queue stats and status,
 * Daily Roster columns, dashboard widgets, sitting notes.
 *
 * Sidebar, page titles, and buttons stay English on purpose.
 * Branding / Web Pages form labels and the Rx pad are a separate pass.
 */
class StaffDeskBanglaTest extends TestCase
{
    private const DESK_FILES = [
        'app/Filament/TenantAdmin/Pages/LiveQueueControl.php',
        'app/Filament/TenantAdmin/Pages/DailyRoster.php',
        'app/Filament/TenantAdmin/Pages/ConsultScreen.php',
        'app/Filament/TenantAdmin/Pages/MissedProcedures.php',
        // Desk labels defined outside the page classes escaped this list
        // entirely, which is how three procedure statuses and the Counseling
        // room label shipped untranslated under a translated column heading.
        'app/Filament/TenantAdmin/Support/StationsHandoffForm.php',
        'app/Filament/TenantAdmin/Support/RosterRecordActions.php',
        'app/Filament/TenantAdmin/Support/QueueRecordActions.php',
        'app/Filament/TenantAdmin/Support/PatientContinuityActions.php',
        'app/Filament/TenantAdmin/Support/CollectFeeAction.php',
        'app/Filament/TenantAdmin/Support/AskReviewAction.php',
        'app/Support/VisitShareCopy.php',
        'resources/views/filament/tenant-admin/components/prescription-share-actions.blade.php',
        'app/Filament/TenantAdmin/Support/OutdoorVitalsAction.php',
        'app/Filament/TenantAdmin/Pages/BookSerial.php',
        'app/Filament/TenantAdmin/Support/StaffBookingForm.php',
        'resources/views/filament/tenant-admin/pages/book-serial.blade.php',
        'resources/views/filament/tenant-admin/pages/book-serial-confirmed.blade.php',
        'app/Services/StationsHandoffService.php',
        'app/Models/Booking.php',
        'app/Models/ScheduleSession.php',
        'app/Filament/TenantAdmin/Widgets/TodayAppointmentsWidget.php',
        'app/Filament/TenantAdmin/Widgets/TenantStatsOverview.php',
        'app/Services/SittingPrompt.php',
        'resources/views/filament/tenant-admin/pages/live-queue-control.blade.php',
        'resources/views/filament/tenant-admin/components/sitting-prompts.blade.php',
        'resources/views/filament/tenant-admin/components/staff-buzz-card.blade.php',
        'resources/views/filament/tenant-admin/components/call-next-nudge.blade.php',
    ];

    public function test_every_staff_desk_string_has_a_bangla_translation(): void
    {
        $translations = json_decode(file_get_contents(base_path('lang/bn.json')), true);

        $this->assertIsArray($translations, 'lang/bn.json is not valid JSON.');

        $missing = [];

        foreach (self::DESK_FILES as $path) {
            $contents = file_get_contents(base_path($path));

            preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $contents, $single);
            preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $contents, $double);

            foreach (array_merge($single[1], $double[1]) as $key) {
                $key = stripcslashes($key);

                if (! array_key_exists($key, $translations)) {
                    $missing[] = basename($path).': '.$key;
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missing)),
            "Staff desk strings with no Bangla translation:\n  ".implode("\n  ", array_unique($missing))
        );
    }
}
