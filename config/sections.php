<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Section Type & Variant Schema
    |--------------------------------------------------------------------------
    |
    | Defines the form fields for each section type/variant combination.
    | The Builder UI reads this to render dynamic forms in SectionEditor.vue.
    |
    | Each variant has:
    |   - label: Human-readable name
    |   - description: Short help text
    |   - fields: Array of form field definitions
    |
    | Field types: text, textarea, wysiwyg, media, number, select, multiselect,
    |              color, url, email, tel, repeater, checkbox, switch
    |
    */

    'hero' => [
        'centered' => [
            'label' => 'Centered Hero',
            'description' => 'Heading + tagline centered with background image',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',     'label' => 'Heading', 'required' => true],
                ['key' => 'tagline',    'type' => 'text',     'label' => 'Tagline'],
                ['key' => 'cta_label',  'type' => 'text',     'label' => 'Button Label'],
                ['key' => 'cta_url',    'type' => 'url',      'label' => 'Button URL'],
                ['key' => 'bg_image',   'type' => 'media',    'label' => 'Background Image'],
            ],
        ],
        'split-image' => [
            'label' => 'Split Image Hero',
            'description' => 'Text left, image right',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',     'label' => 'Heading', 'required' => true],
                ['key' => 'tagline',    'type' => 'text',     'label' => 'Tagline'],
                ['key' => 'cta_label',  'type' => 'text',     'label' => 'Button Label'],
                ['key' => 'cta_url',    'type' => 'url',      'label' => 'Button URL'],
                ['key' => 'image',      'type' => 'media',    'label' => 'Image'],
            ],
        ],
        'video-bg' => [
            'label' => 'Video Background Hero',
            'description' => 'Full-width hero with YouTube/mp4 background',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',     'label' => 'Heading', 'required' => true],
                ['key' => 'tagline',    'type' => 'text',     'label' => 'Tagline'],
                ['key' => 'video_url',  'type' => 'url',      'label' => 'YouTube / MP4 URL'],
                ['key' => 'overlay_opacity', 'type' => 'number', 'label' => 'Overlay Opacity (0-100)', 'default' => 50],
                ['key' => 'cta_label',  'type' => 'text',     'label' => 'Button Label'],
                ['key' => 'cta_url',    'type' => 'url',      'label' => 'Button URL'],
            ],
        ],
        'minimal' => [
            'label' => 'Minimal Hero',
            'description' => 'Text only, no image',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',     'label' => 'Heading', 'required' => true],
                ['key' => 'tagline',    'type' => 'text',     'label' => 'Tagline'],
            ],
        ],
        'with-quicklinks' => [
            'label' => 'Hero with Quick Links',
            'description' => 'Hero with 3 CTA buttons below (Admission, Gallery, Contact)',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',     'label' => 'Heading', 'required' => true],
                ['key' => 'tagline',    'type' => 'text',     'label' => 'Tagline'],
                ['key' => 'bg_image',   'type' => 'media',    'label' => 'Background Image'],
                ['key' => 'links',      'type' => 'repeater', 'label' => 'Quick Links',
                 'fields' => [
                     ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                     ['key' => 'url',   'type' => 'url',  'label' => 'URL'],
                     ['key' => 'icon',  'type' => 'text', 'label' => 'Emoji Icon'],
                 ]],
            ],
        ],
        'sahodaya-centered' => [
            'label' => 'Sahodaya Centered Hero',
            'description' => 'With affiliated board + cluster info',
            'fields' => [
                ['key' => 'heading',         'type' => 'text', 'label' => 'Heading'],
                ['key' => 'tagline',         'type' => 'text', 'label' => 'Tagline'],
                ['key' => 'affiliated_board','type' => 'text', 'label' => 'Affiliated Board'],
                ['key' => 'cluster_info',    'type' => 'text', 'label' => 'Cluster Info'],
                ['key' => 'cta_label',       'type' => 'text', 'label' => 'CTA Label'],
                ['key' => 'cta_url',         'type' => 'url',  'label' => 'CTA URL'],
            ],
        ],
        'event-promo' => [
            'label' => 'Event Promo Hero',
            'description' => 'Kalotsav/Sports Meet promotional hero',
            'fields' => [
                ['key' => 'heading',             'type' => 'text', 'label' => 'Heading'],
                ['key' => 'event_label',         'type' => 'text', 'label' => 'Event Label'],
                ['key' => 'date',                'type' => 'text', 'label' => 'Date'],
                ['key' => 'venue',               'type' => 'text', 'label' => 'Venue'],
                ['key' => 'cta_label',           'type' => 'text', 'label' => 'Primary CTA Label'],
                ['key' => 'cta_url',             'type' => 'url',  'label' => 'Primary CTA URL'],
                ['key' => 'secondary_cta_label', 'type' => 'text', 'label' => 'Secondary CTA Label'],
                ['key' => 'secondary_cta_url',   'type' => 'url',  'label' => 'Secondary CTA URL'],
            ],
        ],
    ],

    'about' => [
        'text-left' => [
            'label' => 'Text Left, Image Right',
            'description' => 'Content on left, image on right',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',     'label' => 'Heading', 'required' => true],
                ['key' => 'content',    'type' => 'wysiwyg',  'label' => 'Content'],
                ['key' => 'image',      'type' => 'media',    'label' => 'Image'],
            ],
        ],
        'text-right' => [
            'label' => 'Image Left, Text Right',
            'description' => 'Image on left, content on right',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',     'label' => 'Heading', 'required' => true],
                ['key' => 'content',    'type' => 'wysiwyg',  'label' => 'Content'],
                ['key' => 'image',      'type' => 'media',    'label' => 'Image'],
            ],
        ],
        'two-column' => [
            'label' => 'Two Column (History + Vision)',
            'description' => 'History left, vision/mission right',
            'fields' => [
                ['key' => 'heading',       'type' => 'text',    'label' => 'Heading'],
                ['key' => 'left_title',    'type' => 'text',    'label' => 'Left Column Title'],
                ['key' => 'left_content',  'type' => 'wysiwyg', 'label' => 'Left Column Content'],
                ['key' => 'right_title',   'type' => 'text',    'label' => 'Right Column Title'],
                ['key' => 'right_content', 'type' => 'wysiwyg', 'label' => 'Right Column Content'],
            ],
        ],
        'with-motto' => [
            'label' => 'With Motto',
            'description' => 'Includes motto, flag, anthem player',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',     'label' => 'Heading'],
                ['key' => 'motto',      'type' => 'text',     'label' => 'School Motto'],
                ['key' => 'content',    'type' => 'wysiwyg',  'label' => 'Content'],
                ['key' => 'image',      'type' => 'media',    'label' => 'Image'],
                ['key' => 'anthem_url', 'type' => 'url',      'label' => 'Anthem Audio URL'],
            ],
        ],
    ],

    'about_sahodaya' => [
        'single-column' => [
            'label' => 'Single Column',
            'description' => 'History, objectives, jurisdiction',
            'fields' => [
                ['key' => 'heading',     'type' => 'text',    'label' => 'Heading'],
                ['key' => 'content',     'type' => 'wysiwyg', 'label' => 'Content'],
                ['key' => 'objectives',  'type' => 'repeater', 'label' => 'Objectives',
                 'fields' => [['key' => 'text', 'type' => 'text', 'label' => 'Objective']]],
            ],
        ],
        'with-stats' => [
            'label' => 'With Stats',
            'description' => 'About + member count stats',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'content', 'type' => 'wysiwyg', 'label' => 'Content'],
                ['key' => 'stats',   'type' => 'repeater', 'label' => 'Statistics',
                 'fields' => [
                     ['key' => 'value', 'type' => 'text', 'label' => 'Value'],
                     ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                 ]],
            ],
        ],
    ],

    'principal_message' => [
        'card-style' => [
            'label' => 'Card Style',
            'description' => 'Photo card + message text',
            'fields' => [
                ['key' => 'heading',    'type' => 'text',   'label' => 'Heading'],
                ['key' => 'name',       'type' => 'text',   'label' => 'Principal Name'],
                ['key' => 'photo',      'type' => 'media',  'label' => 'Photo'],
                ['key' => 'message',    'type' => 'wysiwyg','label' => 'Message'],
                ['key' => 'qualification', 'type' => 'text','label' => 'Qualification'],
            ],
        ],
        'full-width' => [
            'label' => 'Full Width',
            'description' => 'Full-width quote layout',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'name',    'type' => 'text', 'label' => 'Principal Name'],
                ['key' => 'photo',   'type' => 'media', 'label' => 'Photo'],
                ['key' => 'message', 'type' => 'wysiwyg', 'label' => 'Message'],
            ],
        ],
        'with-management' => [
            'label' => 'With Management',
            'description' => 'Principal + chairman + director trio',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'members', 'type' => 'repeater', 'label' => 'Management Members',
                 'fields' => [
                     ['key' => 'name',  'type' => 'text',  'label' => 'Name'],
                     ['key' => 'role',  'type' => 'text',  'label' => 'Role'],
                     ['key' => 'photo', 'type' => 'media', 'label' => 'Photo'],
                     ['key' => 'message', 'type' => 'textarea', 'label' => 'Message'],
                 ]],
            ],
        ],
    ],

    'management' => [
        'photo-cards' => [
            'label' => 'Photo Cards',
            'description' => 'Grid of leadership cards',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'members', 'type' => 'repeater', 'label' => 'Members',
                 'fields' => [
                     ['key' => 'name',        'type' => 'text',  'label' => 'Name'],
                     ['key' => 'designation', 'type' => 'text',  'label' => 'Designation'],
                     ['key' => 'photo',       'type' => 'media', 'label' => 'Photo'],
                 ]],
            ],
        ],
        'table-list' => [
            'label' => 'Table List',
            'description' => 'Name, designation table format',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'members', 'type' => 'repeater', 'label' => 'Members',
                 'fields' => [
                     ['key' => 'name',        'type' => 'text', 'label' => 'Name'],
                     ['key' => 'designation', 'type' => 'text', 'label' => 'Designation'],
                 ]],
            ],
        ],
    ],

    'statistics' => [
        'counter-cards' => [
            'label' => 'Counter Cards',
            'description' => 'Animated scroll-trigger counters',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'stats',   'type' => 'repeater', 'label' => 'Statistics',
                 'fields' => [
                     ['key' => 'value', 'type' => 'text', 'label' => 'Value'],
                     ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                     ['key' => 'icon',  'type' => 'text', 'label' => 'Emoji Icon'],
                 ]],
            ],
        ],
        'horizontal-strip' => [
            'label' => 'Horizontal Strip',
            'description' => 'Single row colored blocks',
            'fields' => [
                ['key' => 'stats', 'type' => 'repeater', 'label' => 'Statistics',
                 'fields' => [
                     ['key' => 'value', 'type' => 'text', 'label' => 'Value'],
                     ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                     ['key' => 'color', 'type' => 'color', 'label' => 'Background Color'],
                 ]],
            ],
        ],
        'with-achievements' => [
            'label' => 'With Achievements',
            'description' => 'Stats + key achievement highlights',
            'fields' => [
                ['key' => 'heading',      'type' => 'text', 'label' => 'Heading'],
                ['key' => 'stats',        'type' => 'repeater', 'label' => 'Statistics',
                 'fields' => [
                     ['key' => 'value', 'type' => 'text', 'label' => 'Value'],
                     ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                 ]],
                ['key' => 'achievements', 'type' => 'repeater', 'label' => 'Achievements',
                 'fields' => [
                     ['key' => 'text', 'type' => 'text', 'label' => 'Achievement'],
                 ]],
            ],
        ],
    ],

    'facilities' => [
        'icon-grid' => [
            'label' => 'Icon Grid',
            'description' => 'Icon + label cards',
            'fields' => [
                ['key' => 'heading',   'type' => 'text', 'label' => 'Heading'],
                ['key' => 'facilities', 'type' => 'repeater', 'label' => 'Facilities',
                 'fields' => [
                     ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                     ['key' => 'icon', 'type' => 'text', 'label' => 'Emoji Icon'],
                 ]],
            ],
        ],
        'image-cards' => [
            'label' => 'Image Cards',
            'description' => 'Photo + title cards',
            'fields' => [
                ['key' => 'heading',   'type' => 'text', 'label' => 'Heading'],
                ['key' => 'facilities', 'type' => 'repeater', 'label' => 'Facilities',
                 'fields' => [
                     ['key' => 'name',  'type' => 'text',  'label' => 'Name'],
                     ['key' => 'photo', 'type' => 'media', 'label' => 'Photo'],
                 ]],
            ],
        ],
        'with-virtual-tour' => [
            'label' => 'With Virtual Tour',
            'description' => 'YouTube 360 embed + facility list',
            'fields' => [
                ['key' => 'heading',    'type' => 'text', 'label' => 'Heading'],
                ['key' => 'tour_url',   'type' => 'url',  'label' => 'Virtual Tour YouTube URL'],
                ['key' => 'facilities', 'type' => 'repeater', 'label' => 'Facilities',
                 'fields' => [
                     ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                 ]],
            ],
        ],
    ],

    'academic_programmes' => [
        'tabs' => [
            'label' => 'Tabs',
            'description' => 'Tabbed by stream (Science, Commerce, Humanities)',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'streams', 'type' => 'repeater', 'label' => 'Streams',
                 'fields' => [
                     ['key' => 'name',        'type' => 'text', 'label' => 'Stream Name'],
                     ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                     ['key' => 'subjects',    'type' => 'text', 'label' => 'Subjects (comma separated)'],
                 ]],
            ],
        ],
        'cards' => [
            'label' => 'Cards',
            'description' => 'One card per stream',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'streams', 'type' => 'repeater', 'label' => 'Streams',
                 'fields' => [
                     ['key' => 'name',        'type' => 'text', 'label' => 'Stream Name'],
                     ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                     ['key' => 'icon',        'type' => 'text', 'label' => 'Emoji Icon'],
                 ]],
            ],
        ],
        'with-results' => [
            'label' => 'With Results',
            'description' => 'Stream info + result stats',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'streams', 'type' => 'repeater', 'label' => 'Streams',
                 'fields' => [
                     ['key' => 'name',        'type' => 'text', 'label' => 'Stream Name'],
                     ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                     ['key' => 'pass_percent','type' => 'text', 'label' => 'Pass Percentage'],
                 ]],
            ],
        ],
    ],

    'staff' => [
        'photo-grid' => [
            'label' => 'Photo Grid',
            'description' => 'Photo + name + designation',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'table-list' => [
            'label' => 'Table List',
            'description' => 'Sortable by department',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'department-tabs' => [
            'label' => 'Department Tabs',
            'description' => 'Tabbed by department',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'news' => [
        'grid' => [
            'label' => 'Grid',
            'description' => '3-column card grid',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'count',   'type' => 'number', 'label' => 'Number of articles to show', 'default' => 6],
            ],
        ],
        'list' => [
            'label' => 'List',
            'description' => 'Article list with date',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'count',   'type' => 'number', 'label' => 'Number of articles to show', 'default' => 10],
            ],
        ],
        'ticker' => [
            'label' => 'Ticker',
            'description' => 'Scrolling marquee for breaking news',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'count',   'type' => 'number', 'label' => 'Number of headlines', 'default' => 5],
            ],
        ],
        'featured-plus-list' => [
            'label' => 'Featured + List',
            'description' => '1 featured + sidebar list',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'events' => [
        'card-grid' => [
            'label' => 'Card Grid',
            'description' => 'Upcoming + past event cards',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'count',   'type' => 'number', 'label' => 'Number of events', 'default' => 6],
            ],
        ],
        'timeline' => [
            'label' => 'Timeline',
            'description' => 'Vertical timeline',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'count',   'type' => 'number', 'label' => 'Number of events', 'default' => 10],
            ],
        ],
        'list' => [
            'label' => 'List',
            'description' => 'Simple date + title list',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'count',   'type' => 'number', 'label' => 'Number of events', 'default' => 10],
            ],
        ],
    ],

    'gallery' => [
        'masonry-grid' => [
            'label' => 'Masonry Grid',
            'description' => 'Masonry layout with lightbox',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'carousel' => [
            'label' => 'Carousel',
            'description' => 'Auto-play image carousel',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'album-based' => [
            'label' => 'Album Based',
            'description' => 'Album thumbnails → album view',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'video_gallery' => [
        'youtube-grid' => [
            'label' => 'YouTube Grid',
            'description' => 'YouTube embed cards',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'videos',  'type' => 'repeater', 'label' => 'Videos',
                 'fields' => [
                     ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                     ['key' => 'url',   'type' => 'url',  'label' => 'YouTube URL'],
                 ]],
            ],
        ],
        'featured-embed' => [
            'label' => 'Featured Embed',
            'description' => 'Single featured video + list',
            'fields' => [
                ['key' => 'heading',     'type' => 'text', 'label' => 'Heading'],
                ['key' => 'featured_url','type' => 'url',  'label' => 'Featured Video URL'],
                ['key' => 'videos',      'type' => 'repeater', 'label' => 'More Videos',
                 'fields' => [
                     ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                     ['key' => 'url',   'type' => 'url',  'label' => 'YouTube URL'],
                 ]],
            ],
        ],
    ],

    'board_results' => [
        'toppers-cards' => [
            'label' => 'Toppers Cards',
            'description' => 'Photo + name + % + class cards',
            'fields' => [
                ['key' => 'heading',    'type' => 'text', 'label' => 'Heading'],
                ['key' => 'show_class', 'type' => 'multiselect', 'label' => 'Classes to show', 'options' => ['10', '12']],
                ['key' => 'years',      'type' => 'number', 'label' => 'Years to show', 'default' => 1],
            ],
        ],
        'stats-plus-toppers' => [
            'label' => 'Stats + Toppers',
            'description' => 'Pass% stats + topper grid',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'year-tabs' => [
            'label' => 'Year Tabs',
            'description' => 'Tabbed by academic year',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'achievements' => [
        'cards' => [
            'label' => 'Cards',
            'description' => 'Card grid, filterable by category',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'timeline' => [
            'label' => 'Timeline',
            'description' => 'Chronological timeline',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'badge-wall' => [
            'label' => 'Badge Wall',
            'description' => 'Certificate photo grid',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'mandatory_disclosure' => [
        'structured' => [
            'label' => 'Structured (CBSE Format)',
            'description' => 'CBSE-required format with all sections',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'accordion' => [
            'label' => 'Accordion',
            'description' => 'Collapsible sections',
            'fields' => [
                ['key' => 'heading',  'type' => 'text', 'label' => 'Heading'],
                ['key' => 'sections', 'type' => 'repeater', 'label' => 'Sections',
                 'fields' => [
                     ['key' => 'title',      'type' => 'text', 'label' => 'Section Title'],
                     ['key' => 'content',    'type' => 'wysiwyg', 'label' => 'Content'],
                     ['key' => 'documents',  'type' => 'repeater', 'label' => 'Documents',
                      'fields' => [
                          ['key' => 'label', 'type' => 'text', 'label' => 'Document Label'],
                          ['key' => 'url',   'type' => 'url',  'label' => 'Document URL'],
                      ]],
                 ]],
            ],
        ],
    ],

    'admissions' => [
        'info-block' => [
            'label' => 'Info Block',
            'description' => 'Procedure + eligibility + contact',
            'fields' => [
                ['key' => 'heading',     'type' => 'text', 'label' => 'Heading'],
                ['key' => 'content',     'type' => 'wysiwyg', 'label' => 'Admission Info'],
                ['key' => 'contact_phone','type' => 'tel', 'label' => 'Contact Phone'],
                ['key' => 'contact_email','type' => 'email', 'label' => 'Contact Email'],
            ],
        ],
        'with-form' => [
            'label' => 'With Form',
            'description' => 'Info + embedded enquiry form',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'fee-structure' => [
            'label' => 'Fee Structure',
            'description' => 'Class-wise fee table',
            'fields' => [
                ['key' => 'heading',    'type' => 'text', 'label' => 'Heading'],
                ['key' => 'subheading', 'type' => 'text', 'label' => 'Subheading'],
                ['key' => 'fee_table',  'type' => 'repeater', 'label' => 'Fee Table Rows',
                 'fields' => [
                     ['key' => 'class',    'type' => 'text', 'label' => 'Class'],
                     ['key' => 'tuition',  'type' => 'text', 'label' => 'Tuition Fee'],
                     ['key' => 'admission','type' => 'text', 'label' => 'Admission Fee'],
                     ['key' => 'total',    'type' => 'text', 'label' => 'Total'],
                 ]],
            ],
        ],
    ],

    'downloads' => [
        'card-grid' => [
            'label' => 'Card Grid',
            'description' => 'File type icon + title + category + download',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'category-tabs' => [
            'label' => 'Category Tabs',
            'description' => 'Tabbed by category',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'alumni' => [
        'registration-form' => [
            'label' => 'Registration Form',
            'description' => 'Alumni signup form',
            'fields' => [
                ['key' => 'heading',    'type' => 'text', 'label' => 'Heading'],
                ['key' => 'subheading', 'type' => 'text', 'label' => 'Subheading'],
            ],
        ],
        'featured-grid' => [
            'label' => 'Featured Grid',
            'description' => 'Notable alumni cards',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'house_system' => [
        'color-cards' => [
            'label' => 'Color Cards',
            'description' => '4 house color cards with names/captains',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'houses',  'type' => 'repeater', 'label' => 'Houses',
                 'fields' => [
                     ['key' => 'name',    'type' => 'text',  'label' => 'House Name'],
                     ['key' => 'color',   'type' => 'color', 'label' => 'Color'],
                     ['key' => 'motto',   'type' => 'text',  'label' => 'Motto'],
                     ['key' => 'captain', 'type' => 'text',  'label' => 'Captain'],
                 ]],
            ],
        ],
        'with-points' => [
            'label' => 'With Points',
            'description' => 'Includes points tally',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'houses',  'type' => 'repeater', 'label' => 'Houses',
                 'fields' => [
                     ['key' => 'name',         'type' => 'text',  'label' => 'House Name'],
                     ['key' => 'color',        'type' => 'color', 'label' => 'Color'],
                     ['key' => 'points',       'type' => 'number','label' => 'Points'],
                     ['key' => 'captain',      'type' => 'text',  'label' => 'Captain'],
                     ['key' => 'vice_captain', 'type' => 'text',  'label' => 'Vice Captain'],
                 ]],
            ],
        ],
    ],

    'clubs' => [
        'icon-grid' => [
            'label' => 'Icon Grid',
            'description' => 'Club name + icon cards',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'clubs',   'type' => 'repeater', 'label' => 'Clubs',
                 'fields' => [
                     ['key' => 'name', 'type' => 'text', 'label' => 'Club Name'],
                     ['key' => 'icon', 'type' => 'text', 'label' => 'Emoji Icon'],
                 ]],
            ],
        ],
        'with-photos' => [
            'label' => 'With Photos',
            'description' => 'Photo + description per club',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'clubs',   'type' => 'repeater', 'label' => 'Clubs',
                 'fields' => [
                     ['key' => 'name',        'type' => 'text',  'label' => 'Club Name'],
                     ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                     ['key' => 'photo',       'type' => 'media', 'label' => 'Photo'],
                 ]],
            ],
        ],
    ],

    'portals' => [
        'quick-links' => [
            'label' => 'Quick Links',
            'description' => 'CampusCare/fee portal/DIKSHA link cards',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'links',   'type' => 'repeater', 'label' => 'Links',
                 'fields' => [
                     ['key' => 'label',       'type' => 'text', 'label' => 'Label'],
                     ['key' => 'url',         'type' => 'url',  'label' => 'URL'],
                     ['key' => 'icon',        'type' => 'text', 'label' => 'Emoji Icon'],
                     ['key' => 'description', 'type' => 'text', 'label' => 'Description'],
                 ]],
            ],
        ],
    ],

    'testimonials' => [
        'carousel' => [
            'label' => 'Carousel',
            'description' => 'Auto-rotating quote cards',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'card-grid' => [
            'label' => 'Card Grid',
            'description' => 'Static grid of testimonials',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'career_guidance' => [
        'info-block' => [
            'label' => 'Info Block',
            'description' => 'Streams, options, counsellor details',
            'fields' => [
                ['key' => 'heading',           'type' => 'text', 'label' => 'Heading'],
                ['key' => 'subheading',        'type' => 'text', 'label' => 'Subheading'],
                ['key' => 'description',       'type' => 'wysiwyg', 'label' => 'Description'],
                ['key' => 'counsellor_name',   'type' => 'text', 'label' => 'Counsellor Name'],
                ['key' => 'counsellor_contact','type' => 'text', 'label' => 'Counsellor Contact'],
                ['key' => 'streams_title',     'type' => 'text', 'label' => 'Streams Section Title'],
                ['key' => 'streams',           'type' => 'repeater', 'label' => 'Streams',
                 'fields' => [
                     ['key' => 'name',        'type' => 'text', 'label' => 'Stream Name'],
                     ['key' => 'description', 'type' => 'text', 'label' => 'Description'],
                     ['key' => 'icon',        'type' => 'text', 'label' => 'Emoji Icon'],
                 ]],
            ],
        ],
    ],

    'publications' => [
        'download-cards' => [
            'label' => 'Download Cards',
            'description' => 'Issue cards with PDF download',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'issues',  'type' => 'repeater', 'label' => 'Issues',
                 'fields' => [
                     ['key' => 'title', 'type' => 'text',  'label' => 'Title'],
                     ['key' => 'date',  'type' => 'text',  'label' => 'Date'],
                     ['key' => 'cover', 'type' => 'media', 'label' => 'Cover Image'],
                     ['key' => 'url',   'type' => 'url',   'label' => 'PDF URL'],
                 ]],
            ],
        ],
    ],

    'atl' => [
        'feature-block' => [
            'label' => 'Feature Block',
            'description' => 'ATL showcase with photos and description',
            'fields' => [
                ['key' => 'heading',    'type' => 'text', 'label' => 'Heading'],
                ['key' => 'description','type' => 'wysiwyg', 'label' => 'Description'],
                ['key' => 'image',      'type' => 'media', 'label' => 'Main Image'],
                ['key' => 'photos',     'type' => 'repeater', 'label' => 'Additional Photos',
                 'fields' => [
                     ['key' => 'photo', 'type' => 'media', 'label' => 'Photo'],
                 ]],
                ['key' => 'features',   'type' => 'repeater', 'label' => 'Features',
                 'fields' => [
                     ['key' => 'text', 'type' => 'text', 'label' => 'Feature'],
                 ]],
            ],
        ],
    ],

    'custom_page' => [
        'freeform' => [
            'label' => 'Freeform',
            'description' => 'WYSIWYG rendered content',
            'fields' => [
                ['key' => 'heading', 'type' => 'text',   'label' => 'Heading'],
                ['key' => 'content', 'type' => 'wysiwyg', 'label' => 'Content'],
            ],
        ],
    ],

    'contact' => [
        'side-by-side' => [
            'label' => 'Side by Side',
            'description' => 'Details left, map right',
            'fields' => [
                ['key' => 'heading',   'type' => 'text', 'label' => 'Heading'],
                ['key' => 'address',   'type' => 'text', 'label' => 'Address'],
                ['key' => 'phone',     'type' => 'tel',  'label' => 'Phone'],
                ['key' => 'email',     'type' => 'email','label' => 'Email'],
                ['key' => 'map_embed', 'type' => 'textarea', 'label' => 'Google Maps Embed HTML'],
            ],
        ],
        'stacked' => [
            'label' => 'Stacked',
            'description' => 'Details above, full-width map',
            'fields' => [
                ['key' => 'heading',   'type' => 'text', 'label' => 'Heading'],
                ['key' => 'address',   'type' => 'text', 'label' => 'Address'],
                ['key' => 'phone',     'type' => 'tel',  'label' => 'Phone'],
                ['key' => 'email',     'type' => 'email','label' => 'Email'],
                ['key' => 'map_embed', 'type' => 'textarea', 'label' => 'Google Maps Embed HTML'],
            ],
        ],
        'with-whatsapp' => [
            'label' => 'With WhatsApp',
            'description' => 'Includes WhatsApp CTA',
            'fields' => [
                ['key' => 'heading',       'type' => 'text', 'label' => 'Heading'],
                ['key' => 'address',       'type' => 'text', 'label' => 'Address'],
                ['key' => 'phone',         'type' => 'tel',  'label' => 'Phone'],
                ['key' => 'email',         'type' => 'email','label' => 'Email'],
                ['key' => 'whatsapp_number','type' => 'tel', 'label' => 'WhatsApp Number'],
                ['key' => 'map_embed',     'type' => 'textarea', 'label' => 'Google Maps Embed HTML'],
            ],
        ],
    ],

    // ── Sahodaya-specific sections ─────────────────────────────────────────────

    'office_bearers' => [
        'photo-cards' => [
            'label' => 'Photo Cards',
            'description' => 'Photo + name + role + term',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'table-list' => [
            'label' => 'Table List',
            'description' => 'Structured table',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'member_schools' => [
        'card-grid' => [
            'label' => 'Card Grid',
            'description' => 'Logo + school name + location + type + link',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'table-list' => [
            'label' => 'Table List',
            'description' => 'Sortable table',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'map-view' => [
            'label' => 'Map View',
            'description' => 'Schools on Google Maps embed',
            'fields' => [
                ['key' => 'heading',   'type' => 'text', 'label' => 'Heading'],
                ['key' => 'map_embed', 'type' => 'textarea', 'label' => 'Google Maps Embed HTML'],
            ],
        ],
    ],

    'news_circulars' => [
        'grid' => [
            'label' => 'Grid',
            'description' => 'Card grid (news + circulars combined)',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'list' => [
            'label' => 'List',
            'description' => 'Dated list',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'events_programs' => [
        'cards' => [
            'label' => 'Cards',
            'description' => 'Upcoming events grid',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'timeline' => [
            'label' => 'Timeline',
            'description' => 'Chronological timeline',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'kalotsav' => [
        'scoreboard' => [
            'label' => 'Scoreboard',
            'description' => 'School-wise results table per event/category',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'results-tabs' => [
            'label' => 'Results Tabs',
            'description' => 'Tabbed by year/category',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'registration-cta' => [
            'label' => 'Registration CTA',
            'description' => 'School login + registration block',
            'fields' => [
                ['key' => 'heading',     'type' => 'text', 'label' => 'Heading'],
                ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                ['key' => 'login_url',   'type' => 'url',  'label' => 'School Login URL'],
                ['key' => 'register_url','type' => 'url',  'label' => 'Register URL'],
            ],
        ],
    ],

    'circulars' => [
        'category-filter' => [
            'label' => 'Category Filter',
            'description' => 'Filterable by category + year',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
        'accordion' => [
            'label' => 'Accordion',
            'description' => 'Grouped by year',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'downloads_sahodaya' => [
        'sahodaya-grid' => [
            'label' => 'Sahodaya Grid',
            'description' => 'Manuals, exam papers, minutes of meetings',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'governance' => [
        'structure' => [
            'label' => 'Structure',
            'description' => 'Org chart + rules + bye-laws downloads',
            'fields' => [
                ['key' => 'heading',  'type' => 'text', 'label' => 'Heading'],
                ['key' => 'org_chart','type' => 'textarea', 'label' => 'Org Chart HTML'],
            ],
        ],
    ],

    'timeline' => [
        'milestone' => [
            'label' => 'Milestone',
            'description' => 'Visual year-by-year milestones',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'testimonials_sahodaya' => [
        'principal-quotes' => [
            'label' => 'Principal Quotes',
            'description' => 'Quotes from member school principals',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

    'job_vacancies' => [
        'listing' => [
            'label' => 'Listing',
            'description' => 'Current vacancies with apply link/email',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
            ],
        ],
    ],

];