<?php

return [
    'fee_models' => [
        'none' => 'No fee',
        'cksc_tiered' => 'CKSC tiered (school registration + per item)',
        'item_catalog' => 'Item catalog (class group / per-item rates)',
        'flat_school' => 'Flat per school',
        'per_item' => 'Flat per item',
        'per_student' => 'Per participating student',
    ],

    'class_group_labels' => [], // deprecated — use FestClassGroupScheme::labels()

    'default_class_group_fees' => [], // deprecated — use FestClassGroupScheme::defaultFees()

    'default_participant_type_fees' => [
        'group' => 150,
        'team' => 150,
    ],

    // Fee catalog rates are year-independent; events/registrations use academic_year_id only.
    'fees_are_year_independent' => true,

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
