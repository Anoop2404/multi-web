<?php

/**
 * Points table for the Confederation of Kerala Sahodaya Complexes' State Kalolsavam
 * ("Keralam State Sahodaya Kalotsavam Manual 2026"), used at both Sahodaya and State
 * level per the manual's instruction that the same manual applies at every level.
 *
 * Derived directly from the manual's "CALCULATION OF POINTS" tables: grade points
 * (A=5, B=3, C=1) plus place points (1st=5, 2nd=3, 3rd=1), summed. Group items use
 * exactly double every number (grade A/B/C=10/6/2, place 1st/2nd/3rd=10/6/2).
 *
 * Distinct from config/fest_mcs_scoring.php — that's a different Sahodaya's (MCS)
 * own custom rules, not this manual's official table. Do not merge the two.
 */
return [
    'grades' => [
        'A' => ['min' => 70, 'label' => 'A'],
        'B' => ['min' => 60, 'label' => 'B'],
        'C' => ['min' => 50, 'label' => 'C'],
    ],

    'individual_points' => [
        'A' => ['1' => 10, '2' => 8, '3' => 6],
        'B' => ['1' => 8, '2' => 6, '3' => 4],
        'C' => ['1' => 6, '2' => 4, '3' => 2],
    ],

    'group_points' => [
        'A' => ['1' => 20, '2' => 16, '3' => 12],
        'B' => ['1' => 16, '2' => 12, '3' => 8],
        'C' => ['1' => 12, '2' => 8, '3' => 4],
    ],

    // The two components individual_points/group_points are already summed from —
    // exposed separately so a report can show "grade points" and "rank/place points"
    // as their own columns instead of just the combined total.
    'grade_points' => [
        'individual' => ['A' => 5, 'B' => 3, 'C' => 1],
        'group' => ['A' => 10, 'B' => 6, 'C' => 2],
    ],

    'place_points' => [
        'individual' => ['1' => 5, '2' => 3, '3' => 1],
        'group' => ['1' => 10, '2' => 6, '3' => 2],
    ],

    'appeal_fee' => 3000,
];
