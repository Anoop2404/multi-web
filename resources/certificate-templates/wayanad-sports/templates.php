<?php

$field = static fn ($text, $top, $left, $width, $size = 20) => [
    'text' => $text, 'top' => $top, 'left' => $left, 'width' => $width,
    'font_size' => $size, 'font_family' => 'Arial', 'font_weight' => 'normal', 'align' => 'center',
];
$base = [
    'event_type' => 'fest', 'body' => '<div></div>',
    'dynamic_fields_json' => [], 'signatories' => [], 'is_active' => true,
];
// Positions are measured against each artwork separately on the 1123 × 794 A4
// canvas. `top` is the line box top (Arial, line-height 1.45), not its baseline.
$layout = [
    'orientation' => 'landscape', 'page' => ['width_mm' => 297, 'height_mm' => 210], 'show_recipient_name' => false,
    'show_certificate_date' => false, 'show_logo_overlay' => false,
    'show_qr' => false, 'show_photo' => false, 'bold_variables' => false,
];

return [
    'winner' => $base + [
        'title' => 'Wayanad Kayikotsavam 2026-27 — Certificate of Merit',
        'certificate_type' => 'winner',
        'layout_json' => $layout + ['custom_fields' => [
            $field('{certificate_date_short}', 39, 67.7, 8.2, 16),
            $field('{recipient_name_upper}', 45, 23.5, 55),
            $field('{class_roman}', 45, 86.2, 8.2),
            $field('{school_name_upper}', 50.2, 7, 87.5),
            $field('{item_title}', 55.5, 23, 71.5),
            $field('{category_name}', 60.8, 14.8, 15.2),
            $field('{position}', 60.8, 43.2, 44),
            $field('26', 71.8, 57.5, 2.7),
            $field('27', 71.8, 63.7, 2.7),
        ]],
    ],
    'participation' => $base + [
        'title' => 'Wayanad Kayikotsavam 2026-27 — Certificate of Participation',
        'certificate_type' => 'participation',
        'layout_json' => $layout + ['custom_fields' => [
            $field('{certificate_date_short}', 39.1, 67.8, 8.2, 16),
            $field('{recipient_name_upper}', 45.2, 23.8, 55),
            $field('{class_roman}', 45.2, 86.3, 8.2),
            $field('{school_name_upper}', 50.4, 7.3, 87.2),
            $field('{item_title}', 55.7, 23.3, 71.2),
            $field('{category_name}', 61.2, 24, 15.2),
            $field('26', 66.5, 70.7, 2.7),
            $field('27', 66.5, 76.7, 2.7),
        ]],
    ],
];
