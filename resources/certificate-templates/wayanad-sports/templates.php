<?php

$field = static fn ($text, $top, $left, $width, $size = 20) => [
    'text' => $text, 'top' => $top, 'left' => $left, 'width' => $width,
    'font_size' => $size, 'font_family' => 'Arial', 'font_weight' => 'normal', 'align' => 'center',
];
$base = [
    'event_type' => 'sports', 'body' => '<div></div>',
    'dynamic_fields_json' => [], 'signatories' => [], 'is_active' => true,
];
$layout = [
    'orientation' => 'landscape', 'show_recipient_name' => false,
    'show_certificate_date' => false, 'show_logo_overlay' => false,
    'show_qr' => false, 'show_photo' => false, 'bold_variables' => false,
];

return [
    'winner' => $base + [
        'title' => 'Wayanad Kayikotsavam 2026-27 — Certificate of Merit',
        'certificate_type' => 'winner',
        'layout_json' => $layout + ['custom_fields' => [
            $field('{certificate_date_short}', 40.3, 68.5, 8.5, 18),
            $field('{recipient_name_upper}', 46.5, 24, 55.5),
            $field('{class_roman}', 46.5, 87.5, 8, 20),
            $field('{school_name_upper}', 52, 7, 88, 20),
            $field('{item_title}', 57.8, 23, 72),
            $field('{category_name}', 63.4, 15, 15.2, 20),
            $field('{position}', 63.4, 44, 44.5),
            $field('26', 75, 58.3, 2, 20),
            $field('27', 75, 64.9, 2, 20),
        ]],
    ],
    'participation' => $base + [
        'title' => 'Wayanad Kayikotsavam 2026-27 — Certificate of Participation',
        'certificate_type' => 'participation',
        'layout_json' => $layout + ['custom_fields' => [
            $field('{certificate_date_short}', 40.3, 68, 8.5, 18),
            $field('{recipient_name_upper}', 46.1, 23, 56),
            $field('{class_roman}', 46.1, 86.5, 8.5, 20),
            $field('{school_name_upper}', 51.9, 6.5, 88.5, 20),
            $field('{item_title}', 57.7, 22.5, 72.5),
            $field('{category_name}', 63.1, 23.2, 15.8, 20),
            $field('26', 69.5, 71.3, 2, 20),
            $field('27', 69.5, 77.5, 2, 20),
        ]],
    ],
];
