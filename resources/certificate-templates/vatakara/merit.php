<?php

return [
    'event_type' => 'fest',
    'certificate_type' => 'winner',
    'title' => 'Vatakara Sahodaya Kalotsav / Kids Fest 2026 - Certificate of Merit',
    'body' => '<div style="color:#222;line-height:1.6;">This is to certify that {salutation} {recipient_name_upper} of {school_name_upper} has {achievement_line} in {item_title}, {category_name}, at {event_title} held at {venue} on {event_dates}.</div>',
    'dynamic_fields_json' => [],
    'signatories' => [],
    'layout_json' => [
        'orientation' => 'landscape',
        'show_recipient_name' => false,
        'show_participation_label' => false,
        'bold_variables' => true,
        'plain_variables' => ['salutation'],
        'show_certificate_date' => false,
        'show_logo_overlay' => false,
        'show_qr' => false,
        'show_photo' => false,
        'body' => [
            'top' => 56, 'left' => 13, 'width' => 74, 'bottom' => 77,
            'font_size' => 20, 'font_family' => 'Times New Roman',
            'font_weight' => 'normal', 'font_style' => 'normal', 'align' => 'left',
        ],
    ],
    'is_active' => true,
];
