<?php

/**
 * MCS Kalotsav -- four-phase / two-level structure config, consumed by
 * fest:configure-phased-structure (docs/MCS_FOUR_PHASE_COMPLETION_PLAN.md §1).
 *
 * Fill in the real tenant id, region codes, and item_phase_map before running with
 * --commit. Region codes must match existing Region.code rows for this Sahodaya; item
 * codes must match existing FestEventItem.item_code rows already on the root event.
 * fest:configure-phased-structure validates all of this and refuses to --commit if
 * anything doesn't resolve, or if any enabled item is left unmapped.
 */
return [
    'tenant_id' => 'REPLACE_WITH_MCS_SAHODAYA_TENANT_ID',

    'batches' => [
        ['code' => 'LEVEL_1', 'name' => 'Level 1 -- Digi Fest & Off Stage', 'school_base_fee' => 4000, 'sort_order' => 1],
        ['code' => 'LEVEL_2', 'name' => 'Level 2 -- Sargadhara & District Kalotsav', 'school_base_fee' => 0, 'sort_order' => 2],
    ],

    'phases' => [
        [
            'code' => 'DIGI',
            'name' => 'Digi Fest',
            'batch_code' => 'LEVEL_1',
            'is_regional' => false,
            'region_codes' => [],
            'sort_order' => 1,
        ],
        [
            'code' => 'OFF_STAGE',
            'name' => 'Off Stage',
            'batch_code' => 'LEVEL_1',
            'is_regional' => true,
            // e.g. ['NILAMBUR', 'TIRUR']
            'region_codes' => [],
            'sort_order' => 2,
        ],
        [
            'code' => 'SARGADHARA',
            'name' => 'Sargadhara',
            'batch_code' => 'LEVEL_2',
            'is_regional' => true,
            // Independent of OFF_STAGE's list -- may overlap, need not match. e.g. ['TIRUR', 'MANJERI']
            'region_codes' => [],
            'sort_order' => 3,
        ],
        [
            'code' => 'DISTRICT',
            'name' => 'District Kalotsav',
            'batch_code' => 'LEVEL_2',
            'is_regional' => false,
            'region_codes' => [],
            'sort_order' => 4,
        ],
    ],

    // Every enabled FestEventItem.item_code on the root event must appear here exactly
    // once, mapped to one of the phase codes above.
    'item_phase_map' => [
        // 'EF101' => 'OFF_STAGE',
        // 'EF102' => 'DIGI',
    ],
];
