<?php

return [
    'placeholders' => [
        '{{school_name}}',
        '{{membership_no}}',
        '{{academic_year}}',
        '{{amount}}',
        '{{amount_words}}',
        '{{payment_method}}',
        '{{transaction_ref}}',
        '{{payment_date}}',
        '{{receipt_no}}',
    ],

    'defaults' => [
        'header_title'       => null, // falls back to Sahodaya name
        'header_subtitle'    => 'An academic forum initiated and guided by the Central Board of Secondary Education, New Delhi.',
        'registered_office'  => null, // falls back to profile address
        'purpose_template'   => 'Annual Sahodaya membership fee for {{academic_year}} (Membership No. {{membership_no}})',
        'receiver_label'     => 'Receiver Signature',
        'counter_label'      => 'Counter Signature',
        'accent_color'       => '#1e3a8a',
        'show_logo'          => true,
        'auto_email_on_verify' => true,
    ],
];
