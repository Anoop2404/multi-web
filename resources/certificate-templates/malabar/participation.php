<?php

$preset = require __DIR__.'/merit.php';
$preset['certificate_type'] = 'participation';
$preset['title'] = 'Malabar Sahodaya District Kalotsav 2026 - Certificate of Participation';
$preset['body'] = '<div style="color:#222;line-height:1.4;text-align:justify;">This is to certify that {salutation} {recipient_name_upper} of {school_name_upper} participated in the Malabar Sahodaya District Kalotsav 2026–27, held at {venue} on {event_dates}, organised by the Sahodaya Schools Complex, Malabar Region.</div>{participation_items_box}';
$preset['layout_json']['body']['align'] = 'left';
$preset['layout_json']['body']['min_font_size'] = 13;
// A template overlay replaces the printed merit heading without modifying the artwork.
$preset['layout_json']['custom_fields'] = [[
    'top' => 41, 'left' => 42, 'width' => 46,
    'font_size' => 32, 'font_family' => 'Times New Roman', 'align' => 'center',
    'text' => '<div style="background:#fff;color:#07864b;font-style:italic;line-height:1.5;padding:4px 0;height:80px;">Certificate of Participation</div>',
]];

return $preset;
