<?php

return [
    'cksc_sahodaya_cluster' => [
        'label' => 'CKSC Sahodaya cluster (3 on + 3 off + 2 group per student)',
        'max_onstage_per_school' => null,
        'max_offstage_per_school' => null,
        'max_group_per_school' => null,
        'max_onstage_per_student' => 3,
        'max_offstage_per_student' => 3,
        'max_group_per_student' => 2,
        'max_total_per_student' => null,
        'one_entry_per_item_per_school' => true,
        'count_submitted_registrations' => true,
        'exclude_standbys_from_limits' => true,
    ],

    'mcs_kalotsav' => [
        'label' => 'MCS Kalotsav (2 off + 3 on OR 3 off + 2 on, max 2 group)',
        'max_onstage_per_school' => null,
        'max_offstage_per_school' => null,
        'max_group_per_school' => null,
        'max_onstage_per_student' => 3,
        'max_offstage_per_student' => 3,
        'max_group_per_student' => 2,
        'max_total_per_student' => null,
        'one_entry_per_item_per_school' => true,
        'count_submitted_registrations' => true,
        'exclude_standbys_from_limits' => true,
        'combo_profiles' => [
            ['onstage' => 3, 'offstage' => 2, 'group' => 2],
            ['onstage' => 2, 'offstage' => 3, 'group' => 2],
        ],
        'regional_max_per_item' => 2,
        'district_max_per_item_per_school' => 1,
    ],

    'cksc_school_kalakriti' => [
        'label' => 'School Kalakriti (3 on + 2 off + 2 group per student)',
        'max_onstage_per_school' => null,
        'max_offstage_per_school' => null,
        'max_group_per_school' => null,
        'max_onstage_per_student' => 3,
        'max_offstage_per_student' => 2,
        'max_group_per_student' => 2,
        'max_total_per_student' => null,
        'one_entry_per_item_per_school' => true,
        'count_submitted_registrations' => true,
        'exclude_standbys_from_limits' => true,
    ],
];
