<?php

namespace App\Support;

/**
 * Common patient-advice taps for the Rx desk Advice card.
 *
 * Chip *labels* follow the English staff panel. The *inserted line* is Bangla
 * because the patient (not the doctor) reads the advice box. The doctor can
 * still type anything in the textarea. Saving a line as "mine" is an explicit
 * tap stored in this browser — not inferred from past prescriptions.
 *
 * @phpstan-type AdviceChip array{label: string, text: string}
 */
class AdviceChips
{
    /**
     * @return list<AdviceChip>
     */
    public static function all(): array
    {
        return [
            ['label' => 'Take after food', 'text' => 'খাবারের পর খান'],
            ['label' => 'Drink plenty of water', 'text' => 'প্রচুর পানি পান করুন'],
            ['label' => 'Avoid spicy food', 'text' => 'ঝাল খাবার এড়িয়ে চলুন'],
            ['label' => 'Rest', 'text' => 'বিশ্রাম নিন'],
            ['label' => 'Come if worse', 'text' => 'অবস্থা খারাপ হলে আবার আসবেন'],
        ];
    }
}
