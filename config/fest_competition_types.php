<?php

/**
 * System competition types (FRD-08 Phase 0).
 * type_key values are stable and match fest_events.event_type / ProgramRouteMap.
 */
return [
    'kalolsavam' => [
        'label' => 'Kalotsav',
        'nav_slug' => 'kalotsav',
        'route_prefix' => 'kalotsav',
        'icon' => 'star',
        'description' => 'Arts & cultural competition',
        'is_singleton' => true,
        'sort_order' => 10,
    ],
    'sports' => [
        'label' => 'Sports Meet',
        'nav_slug' => 'sports-meet',
        'route_prefix' => 'sports',
        'icon' => 'award',
        'description' => 'Athletics and games season hub',
        'is_singleton' => true,
        'sort_order' => 20,
    ],
    'kids_fest' => [
        'label' => 'Kids Fest',
        'nav_slug' => 'kids-fest',
        'route_prefix' => 'kids-fest',
        'icon' => 'users',
        'description' => 'Junior co-curricular festival',
        'is_singleton' => true,
        'sort_order' => 30,
    ],
    'teacher_fest' => [
        'label' => 'Teacher Fest',
        'nav_slug' => 'teacher-fest',
        'route_prefix' => 'teacher-fest',
        'icon' => 'users',
        'description' => 'Teacher talent festival',
        'is_singleton' => true,
        'sort_order' => 40,
    ],
    'english_fest' => [
        'label' => 'English Fest',
        'nav_slug' => 'english-fest',
        'route_prefix' => 'english-fest',
        'icon' => 'file-text',
        'description' => 'English language & literary fest',
        'is_singleton' => true,
        'sort_order' => 50,
    ],
    'science_fest' => [
        'label' => 'Science Fest',
        'nav_slug' => 'science-fest',
        'route_prefix' => 'science-fest',
        'icon' => 'layers',
        'description' => 'Science & exhibition fest',
        'is_singleton' => true,
        'sort_order' => 60,
    ],
    'custom' => [
        'label' => 'Custom Events',
        'nav_slug' => 'custom',
        'route_prefix' => 'custom',
        'icon' => 'calendar',
        'description' => 'Ad-hoc / custom competitions (Phase 1 builder will add named types here)',
        'is_singleton' => false,
        'sort_order' => 100,
    ],
];
