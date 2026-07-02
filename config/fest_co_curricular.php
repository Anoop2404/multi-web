<?php

/**
 * CBSE co-curricular grouping rules (Kalotsav, Kids Fest, Sports).
 *
 * Academic year applies to events and registrations only.
 * Item fee rates come from fest_fees / state program defaults — not tied to academic year.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Kalotsav / Youth Fest — class categories (CBSE Category 1–4)
    | Internal codes lp/up/hs/hss map to Category I–IV in fest_class_group_schemes (cbse).
    | Classes 1–2 are excluded — use Kids Fest instead.
    |--------------------------------------------------------------------------
    */
    'kalolsav' => [
        'min_class' => 3,
        'max_class' => 12,
        'class_to_group' => [
            3  => 'lp',
            4  => 'lp',
            5  => 'up',
            6  => 'up',
            7  => 'up',
            8  => 'hs',
            9  => 'hs',
            10 => 'hs',
            11 => 'hss',
            12 => 'hss',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kids Fest — Pre-KG through Class 2
    |--------------------------------------------------------------------------
    */
    'kids_fest' => [
        'bands' => [
            'pre_kg' => 'Pre-KG / Play School',
            'lkg'    => 'LKG',
            'ukg'    => 'UKG',
            'class1' => 'Class 1',
            'class2' => 'Class 2 (Kiddies)',
            'open'   => 'Open / All Kids Fest bands',
        ],
        'class_name_patterns' => [
            'pre_kg' => ['nursery', 'pre-kg', 'pre kg', 'play school', 'playgroup', 'prekg'],
            'lkg'    => ['lkg', 'lower kg', 'lower kindergarten'],
            'ukg'    => ['ukg', 'upper kg', 'upper kindergarten'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sports — age as on 31 December of the competition year (CBSE / Sahodaya).
    | Student is eligible for Under-N when age on that date is less than N.
    | Each student is assigned one category (U14, U17, U19, etc.) — the tightest band they fit.
    | Override reference date per event via fest_events.sports_age_cutoff_date.
    |--------------------------------------------------------------------------
    */
    'sports' => [
        'cutoff_month_day' => '12-31',
        'age_groups' => [
            'u8'  => ['label' => 'Under 8 (LP Mini)', 'under_age' => 8],
            'u10' => ['label' => 'Under 10 (LP Kiddies)', 'under_age' => 10],
            'u11' => ['label' => 'Under 11', 'under_age' => 11],
            'u12' => ['label' => 'Under 12 (Kiddies)', 'under_age' => 12],
            'u14' => ['label' => 'Under 14', 'under_age' => 14],
            'u17' => ['label' => 'Under 17', 'under_age' => 17],
            'u19' => ['label' => 'Under 19', 'under_age' => 19],
            'open' => ['label' => 'Open / All age groups', 'under_age' => null],
        ],
    ],

];
