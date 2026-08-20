<?php

namespace App\Support;

/**
 * English amount-in-words for a BD medicine voucher ("In Word").
 * Uses lakh / crore grouping, the way a Chattogram pad is filled in.
 */
final class TakaWords
{
    /** @var array<int, string> */
    private const BELOW_TWENTY = [
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    /** @var array<int, string> */
    private const TENS = [
        20 => 'Twenty',
        30 => 'Thirty',
        40 => 'Forty',
        50 => 'Fifty',
        60 => 'Sixty',
        70 => 'Seventy',
        80 => 'Eighty',
        90 => 'Ninety',
    ];

    public static function english(int $taka): string
    {
        if ($taka < 0) {
            return 'Taka Minus '.self::chunk(abs($taka)).' Only';
        }

        if ($taka === 0) {
            return 'Taka Zero Only';
        }

        return 'Taka '.self::chunk($taka).' Only';
    }

    private static function chunk(int $n): string
    {
        if ($n >= 10000000) {
            $crore = intdiv($n, 10000000);
            $rest = $n % 10000000;

            return trim(self::belowThousand($crore).' Crore'.($rest > 0 ? ' '.self::chunk($rest) : ''));
        }

        if ($n >= 100000) {
            $lakh = intdiv($n, 100000);
            $rest = $n % 100000;

            return trim(self::belowThousand($lakh).' Lakh'.($rest > 0 ? ' '.self::chunk($rest) : ''));
        }

        if ($n >= 1000) {
            $thousand = intdiv($n, 1000);
            $rest = $n % 1000;

            return trim(self::belowThousand($thousand).' Thousand'.($rest > 0 ? ' '.self::belowThousand($rest) : ''));
        }

        return self::belowThousand($n);
    }

    private static function belowThousand(int $n): string
    {
        if ($n <= 0) {
            return '';
        }

        if ($n >= 100) {
            $hundreds = intdiv($n, 100);
            $rest = $n % 100;

            return trim(self::BELOW_TWENTY[$hundreds].' Hundred'.($rest > 0 ? ' '.self::belowHundred($rest) : ''));
        }

        return self::belowHundred($n);
    }

    private static function belowHundred(int $n): string
    {
        if ($n <= 0) {
            return '';
        }

        if ($n < 20) {
            return self::BELOW_TWENTY[$n];
        }

        $tens = intdiv($n, 10) * 10;
        $ones = $n % 10;

        return $ones === 0
            ? self::TENS[$tens]
            : self::TENS[$tens].' '.self::BELOW_TWENTY[$ones];
    }
}
