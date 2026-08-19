<?php

namespace App\Support;

use App\Models\User;

/**
 * Which desk buttons a Staff login may use — money, queue, prep — plus lead
 * desk hiring. Null/empty desk_jobs means all three (legacy one-desk behaviour).
 * A login with exactly one job opens that page after sign-in.
 */
final class StaffDeskJobs
{
    public const JOB_MONEY = 'money';

    public const JOB_QUEUE = 'queue';

    public const JOB_PREP = 'prep';

    /** @var list<string> */
    public const ALL_JOBS = [
        self::JOB_MONEY,
        self::JOB_QUEUE,
        self::JOB_PREP,
    ];

    /**
     * @return list<string>
     */
    public static function jobsFor(User $user): array
    {
        if (! $user->isStaff()) {
            return [];
        }

        if ($user->desk_is_lead) {
            return self::ALL_JOBS;
        }

        $stored = $user->desk_jobs;
        if ($stored === null || $stored === []) {
            return self::ALL_JOBS;
        }

        return array_values(array_intersect(self::ALL_JOBS, (array) $stored));
    }

    public static function hasJob(User $user, string $job): bool
    {
        return in_array($job, self::jobsFor($user), true);
    }

    public static function isLeadDesk(User $user): bool
    {
        return $user->isStaff() && (bool) $user->desk_is_lead;
    }

    public static function canCollectFee(User $user): bool
    {
        if ($user->isAdmin() || $user->isDoctor()) {
            return $user->canManageCash();
        }

        if (! $user->isStaff()) {
            return false;
        }

        return self::hasJob($user, self::JOB_MONEY) && $user->canManageCash();
    }

    public static function canRunQueue(User $user): bool
    {
        if ($user->isDoctor()) {
            return $user->canOperateQueueControls();
        }

        if (! $user->isStaff()) {
            return false;
        }

        return self::hasJob($user, self::JOB_QUEUE) && $user->canOperateQueueControls();
    }

    public static function canRecordPrep(User $user): bool
    {
        if (! $user->isStaff()) {
            return false;
        }

        return self::hasJob($user, self::JOB_PREP) && $user->canWorkDesk();
    }

    /**
     * Filament page relative name for a staff login that has exactly one desk
     * job. Null means the stats dashboard (all three jobs, lead, or mixed).
     */
    public static function loginPageRelativeName(User $user): ?string
    {
        if (! $user->isStaff() || $user->desk_is_lead) {
            return null;
        }

        $jobs = self::jobsFor($user);
        if (count($jobs) !== 1) {
            return null;
        }

        return match ($jobs[0]) {
            self::JOB_QUEUE => 'pages.live-queue-control',
            self::JOB_MONEY => 'pages.cashbook',
            self::JOB_PREP => 'pages.daily-roster',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function jobOptions(): array
    {
        return [
            self::JOB_MONEY => __('Money — collect fee and cashbook'),
            self::JOB_QUEUE => __('Queue — call next and live queue'),
            self::JOB_PREP => __('Prep — outdoor vitals and room prep'),
        ];
    }

    /**
     * @param  list<string>|null  $jobs
     * @return list<string>|null null = all jobs (store as null in DB)
     */
    public static function normalizeJobsForStorage(?array $jobs): ?array
    {
        if ($jobs === null || $jobs === []) {
            return null;
        }

        $filtered = array_values(array_intersect(self::ALL_JOBS, $jobs));

        if ($filtered === [] || count($filtered) === count(self::ALL_JOBS)) {
            return null;
        }

        sort($filtered);

        return $filtered;
    }

    /**
     * @return list<string>
     */
    public static function labelsFor(User $user): array
    {
        if (! $user->isStaff()) {
            return [];
        }

        if ($user->desk_is_lead) {
            return [__('Lead desk')];
        }

        $jobs = self::jobsFor($user);
        if (count($jobs) === count(self::ALL_JOBS)) {
            return [__('All desk jobs')];
        }

        return array_map(
            fn (string $job): string => match ($job) {
                self::JOB_MONEY => __('Money'),
                self::JOB_QUEUE => __('Queue'),
                self::JOB_PREP => __('Prep'),
                default => $job,
            },
            $jobs,
        );
    }
}
