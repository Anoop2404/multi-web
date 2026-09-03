<?php

return [
    'fee_models' => [
        'none' => 'No fee',
        'sports_composite' => 'Sports composite (school + student + included items)',
        'kalolsavam_composite' => 'Kalotsavam composite (school + student + included items)',
        'cksc_tiered' => 'CKSC tiered (school registration + per item)',
        'item_catalog' => 'Item catalog (class group / per-item rates)',
        'flat_school' => 'Flat per school',
        'per_item' => 'Flat per item',
        'per_student' => 'Per participating student',
        'student_count_slab' => 'Student count slab (stepped fee by total registered students)',
    ],

    'class_group_labels' => [], // deprecated — use FestClassGroupScheme::labels()

    'default_class_group_fees' => [], // deprecated — use FestClassGroupScheme::defaultFees()

    'default_participant_type_fees' => [
        'group' => 150,
        'team' => 150,
    ],

    // Fee catalog rates are year-independent; events/registrations use academic_year_id only.
    'fees_are_year_independent' => true,

    'presets' => [
        'kochi_metro' => [
            'name' => 'Kochi Metro Sahodaya',
            'fee_model' => 'kalolsavam_composite',
            'include_school_registration' => true,
            'school_registration' => [
                'senior_secondary' => 8000,
                'secondary' => 7000,
                'other' => 7000,
            ],
            'per_student_amount' => 100,
            'included_items_per_student' => 1,
            'extra_item_fee' => 100,
        ],
        'wayanad' => [
            'name' => 'Wayanad Sahodaya',
            'fee_model' => 'per_student',
            'membership_fee' => 5000,
            'school_registration' => [
                'senior_secondary' => 30000,
                'secondary' => 25000,
                'other' => 20000,
            ],
            'phase_1_per_student' => 250,
            'phase_2_per_student' => 250,
        ],
        'malabar' => [
            'name' => 'Malabar Sahodaya',
            'fee_model' => 'student_count_slab',
            'per_student_amount' => 450,
            'student_count_slabs' => [
                ['min_count' => 1, 'max_count' => 49, 'amount' => 6000],
                ['min_count' => 50, 'max_count' => 99, 'amount' => 8000],
                ['min_count' => 100, 'max_count' => 149, 'amount' => 10000],
                ['min_count' => 150, 'max_count' => null, 'amount' => 12000],
            ],
        ],
        'kannur_dist' => [
            'name' => 'CBSE Kannur Dist Kalotsav',
            'fee_model' => 'item_catalog',
            'default_item_fee' => 250,
            'group_item_flat_fee' => 250,
            'group_item_per_participant_rate' => 100,
        ],
    ],

    'level_defaults' => [
        'state' => [
            'fee_model' => 'none',
        ],
        'sahodaya' => [
            'fee_model' => 'cksc_tiered',
            'include_school_registration' => false,
            'school_registration' => [
                'secondary' => 5000,
                'senior_secondary' => 6000,
            ],
            'first_item' => 350,
            'additional_item' => 100,
            'charge_standbys' => false,
        ],
        'sports' => [
            'fee_model' => 'sports_composite',
            'school_registration_flat' => 0,
            'per_student_amount' => 0,
            'included_items_per_student' => 0,
            'default_item_fee' => 0,
        ],
        'school' => [
            'fee_model' => 'none',
        ],
    ],

    'level_labels' => [
        'state' => 'State round',
        'sahodaya' => 'Sahodaya cluster round',
        'school' => 'School round',
    ],

    'payer_labels' => [
        'state' => 'Collected by state (via Sahodaya remittance)',
        'sahodaya' => 'School pays Sahodaya',
        'school' => 'Internal school event — no Sahodaya fee',
    ],
];
