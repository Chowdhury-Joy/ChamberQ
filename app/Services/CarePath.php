<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\ScheduleSession;

/**
 * Which floor path a Stations patient is on.
 *
 * Visit (new): from Visit, staff pick Lab or Intervention, then the type.
 *   Lab still continues to Intervention → Report → Counseling.
 *   Skipping lab goes Visit → Intervention → Report → Counseling.
 * Follow-up (clinic/doctor PracticeRules): Visit → Lab → Report
 *   or Visit → Intervention → Counseling
 * Direct intervention: Intervention → Counseling
 * MSK-only (referred scan): MSK → Report
 *
 * Missing rooms are skipped. Clinics with only Visit / Intervention /
 * Counseling keep the short path.
 */
class CarePath
{
    public const VISIT = 'visit';

    public const FOLLOW_UP = 'follow_up';

    public const INTERVENTION = 'intervention';

    public const MSK = 'msk';

    public const BRANCH_MSK = 'msk';

    public const BRANCH_INTERVENTION = 'intervention';

    public static function isFollowUpEligible(?Patient $patient, ?\App\Models\Doctor $doctor = null): bool
    {
        return PracticeRules::isFollowUpEligible($patient, $doctor);
    }

    public static function forNewSitting(ScheduleSession $session, Patient $patient): string
    {
        if ($session->isInterventionKind()) {
            return self::INTERVENTION;
        }

        if ($session->kind === ScheduleSession::KIND_MSK) {
            return self::MSK;
        }

        if (in_array($session->kind, [ScheduleSession::KIND_VISIT, ScheduleSession::KIND_CONSULT, null, ''], true)) {
            $session->loadMissing('doctor');

            return self::isFollowUpEligible($patient, $session->doctor) ? self::FOLLOW_UP : self::VISIT;
        }

        return self::VISIT;
    }

    /**
     * @return list<string>
     */
    public static function sequence(string $path, ?string $branch): array
    {
        return match ($path) {
            self::FOLLOW_UP => match ($branch) {
                self::BRANCH_MSK => [
                    ScheduleSession::KIND_VISIT,
                    ScheduleSession::KIND_MSK,
                    ScheduleSession::KIND_REPORT,
                ],
                self::BRANCH_INTERVENTION => [
                    ScheduleSession::KIND_VISIT,
                    ScheduleSession::KIND_INTERVENTION,
                    ScheduleSession::KIND_COUNSELING,
                ],
                default => [],
            },
            self::MSK => [
                ScheduleSession::KIND_MSK,
                ScheduleSession::KIND_REPORT,
            ],
            self::INTERVENTION => [
                ScheduleSession::KIND_INTERVENTION,
                ScheduleSession::KIND_COUNSELING,
            ],
            default => [
                ScheduleSession::KIND_VISIT,
                ScheduleSession::KIND_MSK,
                ScheduleSession::KIND_INTERVENTION,
                ScheduleSession::KIND_REPORT,
                ScheduleSession::KIND_COUNSELING,
            ],
        };
    }
}
