<?php

// Coordinates use the unchanged first page of the supplied A4 hall-ticket PDF.
return [
    'layout' => 'custom_background',
    'background_aspect_ratio' => 595.2 / 841.92,
    'card_width_mm' => 190,
    'card_height_mm' => 269,
    'page_orientation' => 'portrait',
    'page_margin_mm' => 10,
    'card_gap_mm' => 0,
    'background_fit' => 'contain',
    'show_exam_date' => false,
    'show_signature' => true,
    'show_controller_sig' => true,
    'show_student_photo' => false,
    'remarks_text' => '',
    'field_positions' => [
        'registration' => ['x' => 24, 'y' => 20.25, 'width' => 64, 'size' => 6.3, 'font_weight' => 'bold'],
        'centre' => ['x' => 32, 'y' => 26.15, 'width' => 57, 'size' => 6.3],
        'name' => ['x' => 34, 'y' => 29.1, 'width' => 55, 'size' => 6.3, 'font_weight' => 'bold'],
        'class' => ['x' => 23, 'y' => 35.0, 'width' => 60, 'size' => 6.3],
        'principal_signature' => ['x' => 64, 'y' => 38.3, 'width' => 22, 'height' => 3.5, 'size' => 6],
        'controller_signature' => ['x' => 12, 'y' => 36.8, 'width' => 18, 'height' => 2.8, 'size' => 6],
    ],
    'custom_fields' => [
        ['id' => 'custom_centre_code', 'label' => 'Centre number', 'source' => 'center_code', 'show_label' => false, 'x' => 33, 'y' => 23.2, 'width' => 55, 'size' => 6.3],
        ['id' => 'custom_gender', 'label' => 'Gender', 'source' => 'gender', 'show_label' => false, 'x' => 23, 'y' => 32.05, 'width' => 60, 'size' => 6.3],
    ],
];
