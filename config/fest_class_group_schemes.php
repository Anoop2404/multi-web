<?php

/**
 * Class group (age category) schemes for fest items and fee catalog.
 *
 * cbse     — Kerala CBSE Kalolsavam official categories (Category I–IV, classes III–XII)
 * sahodaya — PRD / cluster standard (LP–HSS, classes I–XII)
 */
return [
    'default' => 'cbse',

    'options' => [
        'cbse' => 'CBSE Kerala (Category I–IV)',
        'sahodaya' => 'Sahodaya standard (LP–HSS)',
        'cluster' => 'Class master (your CATEGORY1–4 setup)',
    ],

    'schemes' => [
        'cbse' => [
            'groups' => [
                'lp' => 'Category 1 — Classes 3 & 4',
                'up' => 'Category 2 — Classes 5, 6 & 7',
                'hs' => 'Category 3 — Classes 8, 9 & 10',
                'hss' => 'Category 4 — Classes 11 & 12',
                'open' => 'Open / All Categories',
            ],
            'default_fees' => [
                'lp' => 100,
                'up' => 150,
                'hs' => 200,
                'hss' => 250,
                'open' => 200,
            ],
        ],
        'sahodaya' => [
            'groups' => [
                'lp' => 'LP — Classes I–IV',
                'up' => 'UP — Classes V–VII',
                'hs' => 'HS — Classes VIII–X',
                'hss' => 'HSS — Classes XI & XII',
                'open' => 'Open / All Classes',
            ],
            'default_fees' => [
                'lp' => 120,
                'up' => 160,
                'hs' => 210,
                'hss' => 260,
                'open' => 200,
            ],
        ],
    ],
];
