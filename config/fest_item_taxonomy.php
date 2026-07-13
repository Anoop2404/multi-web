<?php

/**
 * Kerala CBSE Sahodaya — event item classification dimensions.
 *
 * Arts (Kalotsav / Kids Fest): sort by stage_type → category → class_group → gender
 * Sports: sort by venue_type → sport_discipline → competition_format → class_group/age → gender
 */
return [

    'stage_type' => [
        'on_stage'  => 'On Stage',
        'off_stage' => 'Off Stage',
    ],

    'venue_type' => [
        'outdoor' => 'Outdoor',
        'indoor'  => 'Indoor',
    ],

    'competition_format' => [
        'individual'      => 'Individual',
        'singles'         => 'Singles',
        'doubles'         => 'Doubles',
        'mixed_doubles'   => 'Mixed Doubles',
        'team'            => 'Team',
        'relay'           => 'Relay',
        'group'           => 'Group',
        'board_game'      => 'Board Game',
    ],

    'sport_discipline' => [
        'track'         => 'Track (Running)',
        'field'         => 'Field (Jump/Throw)',
        'relay'         => 'Relay',
        'march_past'    => 'March Past',
        'team_game'     => 'Team Games',
        'racket'        => 'Racket Sports',
        'board_game'    => 'Board Games',
        'martial_arts'  => 'Martial Arts',
        'aquatics'      => 'Swimming / Aquatics',
        'gymnastics'    => 'Gymnastics / Yoga',
    ],

    'arts_category' => [
        'music'       => 'Music',
        'dance'       => 'Dance',
        'drama'       => 'Drama & Expression',
        'literary'    => 'Literary',
        'fine_arts'   => 'Fine Arts',
        'traditional' => 'Traditional Arts',
        'technology'  => 'New Generation / Tech',
        'general'     => 'General',
    ],

    'class_group' => [
        'lp'   => 'Category I — Classes III & IV',
        'up'   => 'Category II — Classes V–VII',
        'hs'   => 'Category III — Classes VIII–X',
        'hss'  => 'Category IV — Classes XI & XII',
        'open' => 'Open / All Categories',
    ],

    'age_group' => [
        'u8'  => 'Under 8 (LP Mini)',
        'u10' => 'Under 10 (LP Kiddies)',
        'u11' => 'Under 11',
        'u12' => 'Under 12 (Kiddies)',
        'u14' => 'Under 14 (Classes VI–VIII)',
        'u17' => 'Under 17 (Classes IX–XI)',
        'u19' => 'Under 19 (Classes XI–XII)',
        'open' => 'Open / All age groups',
    ],

    'kids_band' => [
        'pre_kg' => 'Pre-KG / Play School',
        'lkg'    => 'LKG',
        'ukg'    => 'UKG',
        'class1' => 'Class 1',
        'class2' => 'Class 2 (Kiddies)',
        'open'   => 'Open / All Kids Fest bands',
    ],

    'gender' => [
        'male'   => 'Boys',
        'female' => 'Girls',
        'mixed'  => 'Mixed / Common',
        'open'   => 'Open',
    ],

    'participant_type' => [
        'individual' => 'Individual',
        'pair'       => 'Pair',
        'trio'       => 'Trio',
        'group'      => 'Group',
        'team'       => 'Team',
    ],

    'result_method' => [
        'marks'     => 'Marks / score',
        'time'      => 'Time (faster wins)',
        'distance'  => 'Distance / measurement',
        'rank'      => 'Rank only',
        'pass_fail' => 'Pass / fail',
        'points'    => 'Points',
    ],

    'sort_order' => [
        'kalolsavam' => ['stage_type', 'category', 'class_group', 'gender', 'title'],
        'sports'     => ['head_key', 'age_group', 'gender', 'title'],
        'kids_fest'  => ['stage_type', 'kids_band', 'category', 'title'],
    ],
];
