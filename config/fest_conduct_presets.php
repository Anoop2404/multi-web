<?php

return [
    'mcs_kalotsav' => [
        'label' => 'MCS Kalotsav (Tirur + Manjeri + District)',
        'conduct_mode' => 'partitioned',
        'event_type' => 'kalotsav',
        'partitions' => [
            [
                'partition_key' => 'tirur',
                'partition_role' => 'region',
                'cluster_label' => 'Tirur Region',
                'level_round' => 'sahodaya',
            ],
            [
                'partition_key' => 'manjeri',
                'partition_role' => 'region',
                'cluster_label' => 'Manjeri Region',
                'level_round' => 'sahodaya',
            ],
            [
                'partition_key' => 'district',
                'partition_role' => 'finale',
                'cluster_label' => 'District Finale',
                'level_round' => 'sahodaya',
            ],
        ],
        'aggregation_config' => [
            'include_roles' => ['region', 'finale'],
            'method' => 'sum_points',
            'overall_label' => 'Overall Championship',
        ],
        'scoring_preset' => 'mcs_kalotsav',
        'participation_preset' => 'mcs_kalotsav',
        'qualifier_policy' => [
            'regional' => ['positions' => [1]],
            'district' => ['positions' => [1, 2]],
            'skip_item_flags' => ['mcs_only'],
        ],
    ],

    'kids_fest' => [
        'label' => 'Kids Fest clusters (legacy)',
        'conduct_mode' => 'partitioned',
        'event_type' => 'kids_fest',
        'use_cluster_key' => true,
        'aggregation_config' => [
            'include_roles' => ['cluster'],
            'method' => 'sum_points',
        ],
    ],
];
