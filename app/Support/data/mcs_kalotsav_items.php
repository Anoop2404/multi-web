<?php

/**
 * MCS Kalotsav item catalog (Malappuram Central Sahodaya pattern).
 * Full item set mirrors CKSC structure with MCS regional/district rules in criteria_json.
 */
$cksc = require __DIR__.'/cksc_kalolsav_items.php';

return array_map(function (array $row) {
    $row['criteria_json'] = array_merge($row['criteria_json'] ?? [], [
        'regional_max_per_item' => 2,
        'district_max_per_item_per_school' => 1,
        'state_eligible' => true,
        'mcs_only' => false,
    ]);

    return $row;
}, $cksc);
