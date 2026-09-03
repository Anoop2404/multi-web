<?php

/**
 * Sports meet age groups (CBSE / Kerala Sahodaya pattern).
 * Age eligibility rules live in config/fest_co_curricular.php (reference date + under_age).
 * Fee amounts are year-independent — see config/fest_fees.php.
 */
return [
    'groups' => [
        'u8'  => 'Under 8 (LP Mini)',
        'u10' => 'Under 10 (LP Kiddies)',
        'u11' => 'Under 11',
        'u12' => 'Under 12 (Kiddies)',
        'u14' => 'Under 14',
        'u16' => 'Under 16',
        'u17' => 'Under 17',
        'u18' => 'Under 18',
        'u19' => 'Under 19',
        'open' => 'Open / All age groups',
    ],

    'default_fees' => [
        'u8'  => 100,
        'u10' => 120,
        'u11' => 130,
        'u12' => 140,
        'u14' => 150,
        'u16' => 180,
        'u17' => 200,
        'u18' => 220,
        'u19' => 250,
        'open' => 200,
    ],

    'class_group_map' => [
        'lp' => 'u14',
        'up' => 'u17',
        'hs' => 'u17',
        'hss' => 'u19',
        'open' => 'open',
    ],
];
