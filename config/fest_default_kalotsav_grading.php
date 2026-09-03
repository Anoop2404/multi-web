<?php

/**
 * The standard Kalotsav grade bands + Grade Points Master table an admin can
 * one-click load onto any event via "Apply Default Kalotsav Grading" (Grade
 * Master / Grade Points Master pages). Distinct from fest_mcs_scoring.php and
 * fest_confed_kalotsav_scoring.php (which back scoring_preset='mcs_kalotsav'/
 * 'confed_kalotsav' and are consulted live at scoring time) -- this one is
 * only ever read by FestEventSettingsController::applyDefaultKalotsavGrading()
 * to seed ordinary, per-event-editable FestGradeConfig/FestPointRule rows, the
 * same way seedConfedKalotsavPoints() does for its own table.
 *
 * grade_bands: event-wide (item_id = null) FestGradeConfig rows. Percent and
 * score bounds are set to the same numbers so the band matches an item
 * whether or not it has a "Total Marks" value configured.
 *
 * point_rules: FestPointRule rows. `grade: null` = "Any Grade" (matches a mark
 * with no grade at all, not a mark literally graded 'No Grade' -- see
 * FestGradePointService::pointsForMark()'s matchesGrade()). `position: null`
 * = "Any Position".
 */
return [
    'grade_bands' => [
        ['grade' => 'A', 'min' => 70, 'max' => 100],
        ['grade' => 'B', 'min' => 60, 'max' => 69],
        ['grade' => 'C', 'min' => 50, 'max' => 59],
        ['grade' => 'No Grade', 'min' => 0, 'max' => 49],
    ],

    'point_rules' => [
        // Grade A
        ['grade' => 'A', 'position' => 1, 'is_group' => false, 'points' => 10],
        ['grade' => 'A', 'position' => 1, 'is_group' => true, 'points' => 20],
        ['grade' => 'A', 'position' => 2, 'is_group' => false, 'points' => 8],
        ['grade' => 'A', 'position' => 2, 'is_group' => true, 'points' => 16],
        ['grade' => 'A', 'position' => 3, 'is_group' => false, 'points' => 6],
        ['grade' => 'A', 'position' => 3, 'is_group' => true, 'points' => 12],
        ['grade' => 'A', 'position' => null, 'is_group' => false, 'points' => 5],
        ['grade' => 'A', 'position' => null, 'is_group' => true, 'points' => 10],

        // Grade B
        ['grade' => 'B', 'position' => 1, 'is_group' => false, 'points' => 8],
        ['grade' => 'B', 'position' => 1, 'is_group' => true, 'points' => 16],
        ['grade' => 'B', 'position' => 2, 'is_group' => false, 'points' => 6],
        ['grade' => 'B', 'position' => 2, 'is_group' => true, 'points' => 12],
        ['grade' => 'B', 'position' => 3, 'is_group' => false, 'points' => 4],
        ['grade' => 'B', 'position' => 3, 'is_group' => true, 'points' => 8],
        ['grade' => 'B', 'position' => null, 'is_group' => false, 'points' => 3],
        ['grade' => 'B', 'position' => null, 'is_group' => true, 'points' => 6],

        // Grade C
        ['grade' => 'C', 'position' => 1, 'is_group' => false, 'points' => 6],
        ['grade' => 'C', 'position' => 1, 'is_group' => true, 'points' => 12],
        ['grade' => 'C', 'position' => 2, 'is_group' => false, 'points' => 4],
        ['grade' => 'C', 'position' => 2, 'is_group' => true, 'points' => 8],
        ['grade' => 'C', 'position' => 3, 'is_group' => false, 'points' => 2],
        ['grade' => 'C', 'position' => 3, 'is_group' => true, 'points' => 4],
        ['grade' => 'C', 'position' => null, 'is_group' => false, 'points' => 1],
        ['grade' => 'C', 'position' => null, 'is_group' => true, 'points' => 2],

        // No Grade (the 0-49 band) -- a real, literal grade value, not the
        // "Any Grade" wildcard. No "Any Position" row: a mark that's both
        // gradeless-of-a-real-grade AND unranked scores 0, deliberately.
        ['grade' => 'No Grade', 'position' => 1, 'is_group' => false, 'points' => 5],
        ['grade' => 'No Grade', 'position' => 1, 'is_group' => true, 'points' => 10],
        ['grade' => 'No Grade', 'position' => 2, 'is_group' => false, 'points' => 3],
        ['grade' => 'No Grade', 'position' => 2, 'is_group' => true, 'points' => 6],
        ['grade' => 'No Grade', 'position' => 3, 'is_group' => false, 'points' => 1],
        ['grade' => 'No Grade', 'position' => 3, 'is_group' => true, 'points' => 2],

        // Any Grade (blank/wildcard) -- covers a mark with a position but no
        // grade value set at all (e.g. a pure rank-only item).
        ['grade' => null, 'position' => 1, 'is_group' => false, 'points' => 5],
        ['grade' => null, 'position' => 1, 'is_group' => true, 'points' => 10],
        ['grade' => null, 'position' => 2, 'is_group' => false, 'points' => 3],
        ['grade' => null, 'position' => 2, 'is_group' => true, 'points' => 6],
        ['grade' => null, 'position' => 3, 'is_group' => false, 'points' => 1],
        ['grade' => null, 'position' => 3, 'is_group' => true, 'points' => 2],
    ],
];
