<?php

/**
 * Default master-catalog browse sections per competition type (event_type).
 * Seeded into fest_taxonomy_masters (dimension=catalog_section) with meta.filter / meta.event_type.
 */
return [
    'sports' => [
        ['slug' => 'track', 'label' => 'Track & cross country', 'description' => 'Running events U8–U19', 'filter' => ['sport_discipline' => 'track']],
        ['slug' => 'field', 'label' => 'Field events', 'description' => 'Jump, throw, vault', 'filter' => ['sport_discipline' => 'field']],
        ['slug' => 'relay', 'label' => 'Relays', 'description' => '4×100m and 4×400m teams', 'filter' => ['sport_discipline' => 'relay']],
        ['slug' => 'team-games', 'label' => 'Team games', 'description' => 'Football, cricket, kabaddi, etc.', 'filter' => ['sport_discipline' => 'team_game']],
        ['slug' => 'racket', 'label' => 'Racket sports', 'description' => 'Badminton, tennis, table tennis', 'filter' => ['sport_discipline' => 'racket']],
        ['slug' => 'board-games', 'label' => 'Board games', 'description' => 'Chess, carrom', 'filter' => ['sport_discipline' => 'board_game']],
        ['slug' => 'martial-arts', 'label' => 'Martial arts', 'description' => 'Judo, taekwondo, wrestling', 'filter' => ['sport_discipline' => 'martial_arts']],
        ['slug' => 'aquatics', 'label' => 'Swimming / aquatics', 'description' => 'Pool events by age', 'filter' => ['sport_discipline' => 'aquatics']],
        ['slug' => 'gymnastics', 'label' => 'Gymnastics', 'description' => 'Artistic gymnastics', 'filter' => ['sport_discipline' => 'gymnastics']],
    ],
    'kalolsavam' => [
        ['slug' => 'cat-1', 'label' => 'Category I (LP)', 'description' => 'Classes III & IV', 'filter' => ['class_group' => 'lp']],
        ['slug' => 'cat-2', 'label' => 'Category II (UP)', 'description' => 'Classes V–VII', 'filter' => ['class_group' => 'up']],
        ['slug' => 'cat-3', 'label' => 'Category III (HS)', 'description' => 'Classes VIII–X', 'filter' => ['class_group' => 'hs']],
        ['slug' => 'cat-4', 'label' => 'Category IV (HSS)', 'description' => 'Classes XI & XII', 'filter' => ['class_group' => 'hss']],
        ['slug' => 'group', 'label' => 'Group items', 'description' => 'Codes 501–511', 'filter' => ['class_group' => 'open']],
    ],
    'kids_fest' => [
        ['slug' => 'pre-kg', 'label' => 'Pre-KG', 'description' => 'Play school / nursery', 'filter' => ['kids_band' => 'pre_kg']],
        ['slug' => 'lkg', 'label' => 'LKG', 'description' => 'Lower kindergarten', 'filter' => ['kids_band' => 'lkg']],
        ['slug' => 'ukg', 'label' => 'UKG', 'description' => 'Upper kindergarten', 'filter' => ['kids_band' => 'ukg']],
        ['slug' => 'class-1', 'label' => 'Class 1', 'description' => 'Kiddies band', 'filter' => ['kids_band' => 'class1']],
        ['slug' => 'class-2', 'label' => 'Class 2', 'description' => 'Kiddies band', 'filter' => ['kids_band' => 'class2']],
    ],
    'teacher_fest' => [
        ['slug' => 'on-stage', 'label' => 'On stage', 'description' => 'Music, dance, drama, speech', 'filter' => ['stage_type' => 'on_stage', 'participant_type' => 'individual']],
        ['slug' => 'off-stage', 'label' => 'Off stage', 'description' => 'Drawing, painting, essay', 'filter' => ['stage_type' => 'off_stage']],
        ['slug' => 'group', 'label' => 'Group items', 'description' => 'Group song, dance, mime', 'filter' => ['participant_type' => 'group']],
    ],
    'english_fest' => [
        ['slug' => 'cat-1', 'label' => 'Category I (LP)', 'description' => 'Classes III & IV', 'filter' => ['class_group' => 'lp']],
        ['slug' => 'cat-2', 'label' => 'Category II (UP)', 'description' => 'Classes V–VII', 'filter' => ['class_group' => 'up']],
        ['slug' => 'cat-3', 'label' => 'Category III (HS)', 'description' => 'Classes VIII–X', 'filter' => ['class_group' => 'hs']],
        ['slug' => 'cat-4', 'label' => 'Category IV (HSS)', 'description' => 'Classes XI & XII', 'filter' => ['class_group' => 'hss']],
        ['slug' => 'group', 'label' => 'Group items', 'description' => 'Group song & drama', 'filter' => ['class_group' => 'open']],
    ],
    'science_fest' => [
        ['slug' => 'cat-1', 'label' => 'Category I (LP)', 'description' => 'Classes III & IV', 'filter' => ['class_group' => 'lp']],
        ['slug' => 'cat-2', 'label' => 'Category II (UP)', 'description' => 'Classes V–VII', 'filter' => ['class_group' => 'up']],
        ['slug' => 'cat-3', 'label' => 'Category III (HS)', 'description' => 'Classes VIII–X', 'filter' => ['class_group' => 'hs']],
        ['slug' => 'cat-4', 'label' => 'Category IV (HSS)', 'description' => 'Classes XI & XII', 'filter' => ['class_group' => 'hss']],
        ['slug' => 'group', 'label' => 'Group items', 'description' => 'Team quiz & exhibition', 'filter' => ['class_group' => 'open']],
    ],
];
