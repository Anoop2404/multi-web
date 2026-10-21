<?php

return [
    'event_type' => 'fest',
    'certificate_type' => 'winner',
    'title' => 'Malabar Sahodaya District Kalotsav 2026 - Certificate of Merit',
    'body' => '<div style="color:#222;line-height:1.5;">This is to certify that {salutation} {recipient_name_upper} of {school_name_upper} has {achievement_line} in {item_title}, under Category {category_short}, at the Malabar Sahodaya District Kalotsav 2026–27, held at {conduct_location} on {event_dates}, organised by the Sahodaya Schools Complex, Malabar Region.</div>',
    'dynamic_fields_json' => [],
    'signatories' => [],
    'layout_json' => [
        'orientation' => 'landscape',
        'page' => ['width_mm' => 297, 'height_mm' => 221.643],
        'show_recipient_name' => false,
        'show_participation_label' => false,
        'bold_variables' => true,
        'plain_variables' => ['salutation'],
        'show_certificate_date' => false,
        'show_logo_overlay' => false,
        'show_qr' => false,
        'show_photo' => false,
        'body' => [
            'top' => 53, 'left' => 35, 'width' => 59, 'bottom' => 22,
            'font_size' => 18, 'min_font_size' => 14, 'font_family' => 'Times New Roman',
            'font_weight' => 'normal', 'font_style' => 'normal', 'align' => 'justify',
        ],
    ],
    'is_active' => true,
];
