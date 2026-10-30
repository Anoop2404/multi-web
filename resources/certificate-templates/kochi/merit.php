<?php

$fields = [];
foreach ([
    ['{certificate_date}', 40.1, 73.4, 9, 10],
    ['{recipient_name_upper}', 45.2, 27.5, 54.5, 19],
    ['{school_name_upper}', 50.5, 13.8, 68.3, 17],
    ['{position}', 56, 20.7, 8.2, 18],
    ['{grade}', 56, 36.9, 10, 18],
    ['{category_roman}', 56, 61, 21.3, 17],
    ['{item_title}', 61.2, 12, 59, 18],
] as [$text, $top, $left, $width, $size]) {
    $fields[] = [
        'text' => $text, 'top' => $top, 'left' => $left, 'width' => $width,
        'font_size' => $size, 'font_family' => 'Times New Roman',
        'font_weight' => 'bold', 'font_style' => 'normal', 'align' => 'center',
    ];
}

return [
    'event_type' => 'fest', 'certificate_type' => 'winner',
    'title' => 'Kochi Sahodaya Utsav 2026 — Certificate of Merit',
    'body' => '<div></div>', 'dynamic_fields_json' => [], 'signatories' => [],
    'layout_json' => [
        'orientation' => 'landscape', 'show_recipient_name' => false,
        'show_participation_label' => true, 'show_certificate_date' => false,
        'show_logo_overlay' => false, 'show_qr' => false, 'show_photo' => false,
        'bold_variables' => false, 'custom_fields' => $fields,
    ],
    'is_active' => true,
];
