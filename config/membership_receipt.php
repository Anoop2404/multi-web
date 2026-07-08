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
        'registered_office'  => null, // falls back to profile address with "Registered office :" prefix
        'society_registration' => null, // e.g. Reg. Under Societies Registration Act 2025 No. MPM/109/2026
        'purpose_template'   => 'Annual Sahodaya membership fee for {{academic_year}} (Membership No. {{membership_no}})',
        'receiver_label'     => 'Receiver Signature',
        'counter_label'      => 'Counter Signature',
        'receipt_signatures_enabled' => true,
        'representatives'    => [
            ['enabled' => true, 'name' => '', 'designation' => 'Receiver Signature', 'signature_path' => null],
            ['enabled' => true, 'name' => '', 'designation' => 'Counter Signature', 'signature_path' => null],
        ],
        'show_seal'          => false,
        'seal_label'         => 'Sahodaya Seal',
        'seal_path'          => null,
        'accent_color'       => '#1e3a8a',
        'show_logo'          => true,
        'auto_email_on_verify' => true,
    ],
];
