<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default partner cuts (fractions, 0.20 = 20%)
    |--------------------------------------------------------------------------
    |
    | Super Admin can override per doctor. ChamberQ keeps whatever is left.
    | Year 1 month-by-month is always 0 unless an override is set.
    |
    */

    'setup' => [
        'with_mr' => [
            'mr' => 0.20,
            'marketer' => 0.20,
        ],
        'direct' => [
            'mr' => 0.0,
            'marketer' => 0.20,
        ],
    ],

    'year1_monthly' => [
        'mr' => 0.0,
        'marketer' => 0.0,
    ],

    'year1_prepaid' => [
        'with_mr' => [
            'mr' => 0.15,
            'marketer' => 0.05,
        ],
        'direct' => [
            'mr' => 0.0,
            'marketer' => 0.20,
        ],
    ],

    'year2' => [
        'with_mr' => [
            'mr' => 0.05,
            'marketer' => 0.05,
        ],
        'direct' => [
            'mr' => 0.0,
            'marketer' => 0.10,
        ],
    ],

];
