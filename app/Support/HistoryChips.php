<?php

namespace App\Support;

/**
 * The past-history taps every doctor starts with on the Rx desk History card.
 *
 * These are clinical shorthand a doctor writes onto the pad, so they are not
 * translated — HTN is HTN on a Bangla panel too. `primary` chips sit in the
 * first row; the rest are behind "More…".
 *
 * Like the advice chips these are a starting point: a doctor edits, hides or
 * extends them on **My medicines**, stored as `DoctorChip` rows keyed by the
 * `key` below. Keep the keys stable.
 *
 * @phpstan-type HistoryChip array{key: string, label: string, primary: bool}
 */
class HistoryChips
{
    /**
     * @return list<HistoryChip>
     */
    public static function all(): array
    {
        return [
            ['key' => 'htn', 'label' => 'HTN', 'primary' => true],
            ['key' => 'dm', 'label' => 'DM', 'primary' => true],
            ['key' => 'asthma', 'label' => 'Asthma', 'primary' => true],
            ['key' => 'ckd', 'label' => 'CKD', 'primary' => false],
            ['key' => 'ihd', 'label' => 'IHD', 'primary' => false],
            ['key' => 'thyroid', 'label' => 'Thyroid', 'primary' => false],
            ['key' => 'smoker', 'label' => 'Smoker', 'primary' => false],
            ['key' => 'copd', 'label' => 'COPD', 'primary' => false],
            ['key' => 'allergy', 'label' => 'Allergy', 'primary' => false],
        ];
    }
}
