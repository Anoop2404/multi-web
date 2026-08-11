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
        'kalotsav_category' => 'Kalotsavam Categories (Category 1–5)',
        'cbse' => 'CBSE Kerala (Category I–IV)',
        'sahodaya' => 'Sahodaya standard (LP–HSS)',
        'cluster' => 'Class master (your CATEGORY1–4 setup)',
        'custom' => 'Custom categories for this event',
    ],

    'schemes' => [
        'kalotsav_category' => [
            'groups' => [
                'category_1' => 'Category 1 — Classes 3 & 4',
                'category_2' => 'Category 2 — Classes 5, 6 & 7',
                'category_3' => 'Category 3 — Classes 8, 9 & 10',
                'category_4' => 'Category 4 — Classes 11 & 12',
                'category_5' => 'Category 5 — Group Items (Open)',
            ],
            'default_fees' => [
                'category_1' => 100,
                'category_2' => 150,
                'category_3' => 200,
                'category_4' => 250,
                'category_5' => 200,
            ],
        ],
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
