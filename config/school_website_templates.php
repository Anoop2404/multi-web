<?php

return [
    'al-farooque' => [
        'name' => 'Al Farooque',
        'version' => '1.0.0',
        'purpose' => 'A warm, photo-forward school site with admissions, achievements and CBSE disclosure front and center.',
        'audience' => 'Prospective and current parents, students and staff',
        'character' => 'Warm, generous whitespace, rounded imagery',
        'accent' => 'Green brand, photo-led',
        'design' => [
            'primary' => '#04906D', 'secondary' => '#037559', 'accent_color' => '#F59E0B',
            'display_font' => 'Inter', 'body_font' => 'Inter', 'type_scale' => 'balanced',
            'density' => 'comfortable', 'surface' => 'elevated', 'corners' => 'soft',
            'buttons' => 'solid', 'images' => 'documentary', 'motion' => 'restrained',
            'navigation' => 'logo-left', 'footer' => 'three-column',
        ],
        'widgets' => ['news_ticker' => false, 'admission_banner' => false, 'social_strip' => true],
        'sections' => [
            ['section_type' => 'hero', 'variant' => 'full-slider', 'config' => [
                'slides' => [
                    ['title' => '{{name}}', 'subtitle' => 'CBSE Affiliated', 'description' => 'Building knowledge, values and confidence.', 'cta_label' => 'Admissions Open', 'cta_url' => '#admission'],
                ],
            ], 'layout' => ['width' => 'full', 'spacing' => 'compact', 'surface' => 'dark', 'heading_alignment' => 'left']],
            ['section_type' => 'about', 'variant' => 'overlap-stats', 'config' => [
                'eyebrow' => 'About Us', 'heading' => 'Building Knowledge, Values, and Confidence',
                'body' => 'Welcome to {{name}} — where academic excellence meets holistic development.',
                'mini_stats' => [
                    ['value' => '2000+', 'label' => 'Students'],
                    ['value' => '80+', 'label' => 'Qualified Staff'],
                    ['value' => 'CBSE', 'label' => 'Affiliated'],
                ],
                'cta_label' => 'Know More', 'cta_url' => '#about',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'facilities', 'variant' => 'why-choose-cards', 'config' => [
                'eyebrow' => 'Why Choose Us', 'heading_line1' => 'Why Families Choose', 'heading_line2' => '{{name}}',
                'items' => [
                    ['title' => 'Academic Excellence', 'description' => 'A strong, well-rounded curriculum.', 'icon' => 'education'],
                    ['title' => 'Safe Campus', 'description' => 'A secure, caring environment.', 'icon' => 'shield'],
                    ['title' => 'Modern Learning', 'description' => 'Facilities built for curiosity.', 'icon' => 'bulb'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'admissions', 'variant' => 'promo-block', 'config' => [
                'badge' => 'Admissions Open', 'heading' => 'Join Our Community',
                'subtitle' => 'Now enrolling for the new academic session',
                'content' => 'Give your child the advantage of quality education and a nurturing environment.',
                'cta_label' => 'Submit enquiry', 'cta_url' => '/admission-enquiry',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'principal_message', 'variant' => 'split-portrait', 'config' => [
                'lead_in' => 'A Message from the Principal', 'designation' => 'Principal',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'achievements', 'variant' => 'horizontal-scroll', 'config' => [
                'eyebrow' => 'Celebrating Success', 'heading' => 'Recent Achievements', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'gallery', 'variant' => 'preview-grid', 'config' => [
                'eyebrow' => 'Life at {{name}}', 'heading' => 'Moments Beyond Learning', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'news', 'variant' => 'featured-strip', 'config' => [
                'heading' => 'News & Events',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'staff', 'variant' => 'card-grid', 'config' => [
                'eyebrow' => 'Meet the Team', 'heading' => 'Our Faculty', 'limit' => 12,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'mandatory_disclosure', 'variant' => 'structured', 'config' => [
                'school_name' => '{{name}}',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'contact', 'variant' => 'centered-card', 'config' => [
                'badge' => 'Get In Touch', 'heading' => 'Contact Us',
                'intro' => 'Have a question? Send us a message and we will get back to you.',
                'form_slug' => 'contact',
            ], 'layout' => ['width' => 'narrow', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'center']],
        ],
    ],
];
