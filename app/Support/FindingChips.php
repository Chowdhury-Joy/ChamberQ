<?php

namespace App\Support;

/**
 * A few O/E findings that write into the Other findings box.
 *
 * Not a hospital grid (Heart / Lungs / Abd / Cyanosis / RBS). Four taps that
 * cover what a chamber GP actually ticks, stored as English in the same
 * `on_examination` text the phone modal and print already use.
 */
class FindingChips
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'Anaemia',
            'Jaundice',
            'Lungs clear',
            'Abdomen soft',
        ];
    }
}
