<?php

namespace App\Support;

/**
 * The advice taps every doctor starts with on the Rx desk Advice card.
 *
 * Chip *labels* follow the English staff panel. The *inserted line* is Bangla
 * because the patient (not the doctor) reads the advice box. The doctor can
 * still type anything in the textarea.
 *
 * These are a starting point, not the list: a doctor edits, hides or extends
 * them on **My medicines**, and those departures are stored per doctor as
 * `DoctorChip` rows keyed by the `key` below. Keep the keys stable — changing
 * one orphans every doctor's edit of that chip.
 *
 * @phpstan-type AdviceChip array{key: string, label: string, text: string}
 */
class AdviceChips
{
    /**
     * @return list<AdviceChip>
     */
    public static function all(): array
    {
        return [
            ['key' => 'after-food', 'label' => 'Take after food', 'text' => 'খাবারের পর খান'],
            ['key' => 'plenty-water', 'label' => 'Drink plenty of water', 'text' => 'প্রচুর পানি পান করুন'],
            ['key' => 'avoid-spicy', 'label' => 'Avoid spicy food', 'text' => 'ঝাল খাবার এড়িয়ে চলুন'],
            ['key' => 'rest', 'label' => 'Rest', 'text' => 'বিশ্রাম নিন'],
            ['key' => 'come-if-worse', 'label' => 'Come if worse', 'text' => 'অবস্থা খারাপ হলে আবার আসবেন'],
        ];
    }
}
