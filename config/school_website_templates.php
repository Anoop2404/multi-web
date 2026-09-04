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
                'stat_value' => '30+', 'stat_label_line1' => 'YEARS OF', 'stat_label_line2' => 'EXCELLENCE',
                'cta_label' => 'Know More', 'cta_url' => '#about',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'facilities', 'variant' => 'why-choose-cards', 'config' => [
                'eyebrow' => 'Why Choose Us',
                'heading_line1' => 'Committed to Quality Education',
                'heading_line2' => 'and Holistic Development',
                'items' => [
                    ['title' => 'Experienced & Dedicated Faculty', 'description' => "Qualified and passionate teachers who inspire, guide and nurture every student's growth.", 'icon' => 'education'],
                    ['title' => 'Safe & Supportive Environment', 'description' => 'A secure, caring campus where students feel valued and empowered to reach their full potential.', 'icon' => 'shield'],
                    ['title' => 'Modern Teaching Methods', 'description' => 'Smart classrooms, interactive learning and technology-driven education for 21st century skills.', 'icon' => 'bulb'],
                    ['title' => 'Moral & Value-Based Education', 'description' => 'Building strong character, integrity, and responsible citizenship alongside academic achievements.', 'icon' => 'heart'],
                    ['title' => 'Co-Curricular & Skill Development', 'description' => 'Sports, arts, cultural events and clubs that develop creativity, teamwork and leadership.', 'icon' => 'smile'],
                    ['title' => 'Student-Centered Approach', 'description' => 'Every student is unique — personalized attention and inclusive methods to help each child thrive.'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'academic_programmes', 'variant' => 'cards', 'config' => [
                'heading' => 'Academic Programme',
                'streams' => [
                    ['icon' => '🎒', 'name' => 'Primary', 'description' => 'Foundational years focused on curiosity, literacy and numeracy.', 'subjects' => ['English', 'Maths', 'EVS', 'Art']],
                    ['icon' => '📚', 'name' => 'Middle School', 'description' => 'Building strong concepts across core subjects and skills.', 'subjects' => ['Science', 'Social Science', 'Maths', 'Languages']],
                    ['icon' => '🎓', 'name' => 'Secondary & Senior Secondary', 'description' => 'CBSE curriculum preparing students for board examinations and beyond.', 'subjects' => ['Science', 'Commerce', 'Humanities']],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'admissions', 'variant' => 'promo-block', 'config' => [
                'badge' => 'Admissions Open', 'heading' => 'Join Our Community',
                'subtitle' => 'Now enrolling for the new academic session',
                'content' => 'Give your child the advantage of quality education and a nurturing environment.',
                'key_dates' => [
                    ['date' => 'Jan', 'label' => 'Admission enquiry opens'],
                    ['date' => 'Mar', 'label' => 'Application deadline'],
                    ['date' => 'Apr', 'label' => 'New academic session begins'],
                ],
                'cta_label' => 'Submit enquiry', 'cta_url' => '/admission-enquiry',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'principal_message', 'variant' => 'split-portrait', 'config' => [
                'lead_in' => 'A Message from the Principal',
                'message' => "Welcome to {{name}}. Every child who walks through our gates is given the encouragement, discipline and care needed to discover their own potential. We believe education is not just about examinations, but about building character, curiosity and confidence for life.",
                'designation' => 'Principal',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'achievements', 'variant' => 'horizontal-scroll', 'config' => [
                'eyebrow' => 'Celebrating Success', 'heading' => 'Recent Achievements', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'board_results', 'variant' => 'stats-plus-toppers', 'config' => [
                'eyebrow' => 'Academic Excellence', 'heading' => 'Board Results',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'gallery', 'variant' => 'preview-grid', 'config' => [
                'eyebrow' => 'Life at {{name}}', 'heading' => 'Moments Beyond Learning', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'house_system', 'variant' => 'color-cards', 'config' => [
                'heading' => 'Our House System',
                'houses' => [
                    ['name' => 'Crimson House', 'color' => '#DC2626', 'motto' => 'Courage in all things'],
                    ['name' => 'Azure House', 'color' => '#2563EB', 'motto' => 'Wisdom above all'],
                    ['name' => 'Emerald House', 'color' => '#16A34A', 'motto' => 'Growth through effort'],
                    ['name' => 'Amber House', 'color' => '#D97706', 'motto' => 'Unity in spirit'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'news', 'variant' => 'featured-strip', 'config' => [
                'heading' => 'News & Events',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'staff', 'variant' => 'card-grid', 'config' => [
                'eyebrow' => 'Meet the Team', 'heading' => 'Our Faculty', 'limit' => 12,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'testimonials', 'variant' => 'card-grid', 'config' => [
                'heading' => 'What Parents Say',
                'testimonials' => [
                    ['name' => 'Parent, {{name}}', 'designation' => 'Class V Parent', 'quote' => 'Our child has grown so much in confidence and curiosity since joining {{name}}. The teachers genuinely care.', 'rating' => 5],
                    ['name' => 'Parent, {{name}}', 'designation' => 'Class IX Parent', 'quote' => 'A nurturing environment with strong academics and great extracurricular exposure.', 'rating' => 5],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
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

    'modern-minimal' => [
        'name' => 'Modern Minimal',
        'version' => '1.0.0',
        'purpose' => 'A clean, contemporary site for schools that want to lead with academics and a sleek digital-first impression.',
        'audience' => 'Tech-savvy parents, students and staff who value clarity over decoration',
        'character' => 'Minimal, generous whitespace, sharp typography, understated interactions',
        'accent' => 'Slate & cyan, monochrome photography',
        'design' => [
            'primary' => '#0F172A', 'secondary' => '#1E293B', 'accent_color' => '#06B6D4',
            'display_font' => 'Manrope', 'body_font' => 'Inter', 'type_scale' => 'compact',
            'density' => 'compact', 'surface' => 'flat', 'corners' => 'square',
            'buttons' => 'understated', 'images' => 'monochrome', 'motion' => 'restrained',
            'navigation' => 'sticky-transparent', 'footer' => 'minimal-single-row',
        ],
        'widgets' => ['news_ticker' => false, 'admission_banner' => false, 'social_strip' => false],
        'sections' => [
            ['section_type' => 'hero', 'variant' => 'minimal', 'config' => [
                'heading' => '{{name}}', 'tagline' => 'Academics first. Everything else follows.',
            ], 'layout' => ['width' => 'full', 'spacing' => 'compact', 'surface' => 'dark', 'heading_alignment' => 'center']],
            ['section_type' => 'about', 'variant' => 'overlap-stats', 'config' => [
                'eyebrow' => 'About', 'heading' => 'A Focused Approach to Education',
                'body' => '{{name}} keeps the focus on what matters — strong teaching, clear expectations, and steady progress for every student.',
                'mini_stats' => [
                    ['value' => '2000+', 'label' => 'Students'],
                    ['value' => '80+', 'label' => 'Qualified Staff'],
                    ['value' => 'CBSE', 'label' => 'Affiliated'],
                ],
                'cta_label' => 'Learn more', 'cta_url' => '#about',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'academic_programmes', 'variant' => 'cards', 'config' => [
                'heading' => 'Academic Programme',
                'streams' => [
                    ['icon' => '🎒', 'name' => 'Primary', 'description' => 'Foundational years focused on curiosity, literacy and numeracy.', 'subjects' => ['English', 'Maths', 'EVS', 'Art']],
                    ['icon' => '📚', 'name' => 'Middle School', 'description' => 'Building strong concepts across core subjects and skills.', 'subjects' => ['Science', 'Social Science', 'Maths', 'Languages']],
                    ['icon' => '🎓', 'name' => 'Secondary & Senior Secondary', 'description' => 'CBSE curriculum preparing students for board examinations and beyond.', 'subjects' => ['Science', 'Commerce', 'Humanities']],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'board_results', 'variant' => 'stats-plus-toppers', 'config' => [
                'eyebrow' => 'Outcomes', 'heading' => 'Board Results',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'facilities', 'variant' => 'why-choose-cards', 'config' => [
                'eyebrow' => 'Why {{name}}', 'heading_line1' => 'Built for', 'heading_line2' => 'Focused Learning',
                'items' => [
                    ['title' => 'Academic Excellence', 'description' => 'A strong, well-rounded curriculum.', 'icon' => 'education'],
                    ['title' => 'Safe Campus', 'description' => 'A secure, caring environment.', 'icon' => 'shield'],
                    ['title' => 'Modern Learning', 'description' => 'Facilities built for curiosity.', 'icon' => 'bulb'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'admissions', 'variant' => 'promo-block', 'config' => [
                'badge' => 'Admissions Open', 'heading' => 'Apply Now',
                'subtitle' => 'Now enrolling for the new academic session',
                'content' => 'A straightforward admissions process, built around your child\'s progress.',
                'key_dates' => [
                    ['date' => 'Jan', 'label' => 'Admission enquiry opens'],
                    ['date' => 'Mar', 'label' => 'Application deadline'],
                    ['date' => 'Apr', 'label' => 'New academic session begins'],
                ],
                'cta_label' => 'Submit enquiry', 'cta_url' => '/admission-enquiry',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'staff', 'variant' => 'card-grid', 'config' => [
                'eyebrow' => 'Faculty', 'heading' => 'Our Teachers', 'limit' => 12,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'gallery', 'variant' => 'preview-grid', 'config' => [
                'eyebrow' => 'Campus', 'heading' => 'Life at {{name}}', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'mandatory_disclosure', 'variant' => 'structured', 'config' => [
                'school_name' => '{{name}}',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'contact', 'variant' => 'centered-card', 'config' => [
                'badge' => 'Contact', 'heading' => 'Get in Touch',
                'intro' => 'Questions? Send us a message.',
                'form_slug' => 'contact',
            ], 'layout' => ['width' => 'narrow', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'center']],
        ],
    ],

    'traditional-institutional' => [
        'name' => 'Traditional Institutional',
        'version' => '1.0.0',
        'purpose' => 'A formal, heritage-forward site for well-established schools proud of their legacy and discipline.',
        'audience' => 'Parents who value tradition, structure and a long institutional track record',
        'character' => 'Formal, symmetrical, serif headings, generous borders',
        'accent' => 'Deep navy & gold, editorial photography',
        'design' => [
            'primary' => '#1E3A5F', 'secondary' => '#14283F', 'accent_color' => '#C9A24B',
            'display_font' => 'Merriweather', 'body_font' => 'Roboto', 'type_scale' => 'editorial',
            'density' => 'spacious', 'surface' => 'bordered', 'corners' => 'square',
            'buttons' => 'bordered', 'images' => 'formal', 'motion' => 'none',
            'navigation' => 'logo-center', 'footer' => 'four-column',
        ],
        'widgets' => ['news_ticker' => true, 'admission_banner' => false, 'social_strip' => true],
        'sections' => [
            ['section_type' => 'hero', 'variant' => 'full-slider', 'config' => [
                'slides' => [
                    ['title' => '{{name}}', 'subtitle' => 'CBSE Affiliated', 'description' => 'A tradition of academic excellence and character.', 'cta_label' => 'Admissions Open', 'cta_url' => '#admission'],
                ],
            ], 'layout' => ['width' => 'full', 'spacing' => 'compact', 'surface' => 'dark', 'heading_alignment' => 'center']],
            ['section_type' => 'about', 'variant' => 'overlap-stats', 'config' => [
                'eyebrow' => 'Our Legacy', 'heading' => 'A Tradition of Excellence',
                'body' => '{{name}} has, for generations, shaped students of strong character and academic distinction.',
                'mini_stats' => [
                    ['value' => '2000+', 'label' => 'Students'],
                    ['value' => '80+', 'label' => 'Qualified Staff'],
                    ['value' => 'CBSE', 'label' => 'Affiliated'],
                ],
                'stat_value' => '25+', 'stat_label_line1' => 'YEARS OF', 'stat_label_line2' => 'SERVICE',
                'cta_label' => 'Our Story', 'cta_url' => '#about',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'principal_message', 'variant' => 'split-portrait', 'config' => [
                'lead_in' => 'A Message from the Principal',
                'message' => 'Welcome to {{name}}. We take pride in a tradition of discipline, scholarship and service that has guided generations of students toward purposeful lives.',
                'designation' => 'Principal',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'academic_programmes', 'variant' => 'cards', 'config' => [
                'heading' => 'Academic Programme',
                'streams' => [
                    ['icon' => '🎒', 'name' => 'Primary', 'description' => 'Foundational years focused on curiosity, literacy and numeracy.', 'subjects' => ['English', 'Maths', 'EVS', 'Art']],
                    ['icon' => '📚', 'name' => 'Middle School', 'description' => 'Building strong concepts across core subjects and skills.', 'subjects' => ['Science', 'Social Science', 'Maths', 'Languages']],
                    ['icon' => '🎓', 'name' => 'Secondary & Senior Secondary', 'description' => 'CBSE curriculum preparing students for board examinations and beyond.', 'subjects' => ['Science', 'Commerce', 'Humanities']],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'facilities', 'variant' => 'why-choose-cards', 'config' => [
                'eyebrow' => 'Our Foundation', 'heading_line1' => 'Why Families Choose', 'heading_line2' => '{{name}}',
                'items' => [
                    ['title' => 'Academic Excellence', 'description' => 'A strong, well-rounded curriculum.', 'icon' => 'education'],
                    ['title' => 'Safe Campus', 'description' => 'A secure, caring environment.', 'icon' => 'shield'],
                    ['title' => 'Discipline & Values', 'description' => 'Character built alongside scholarship.', 'icon' => 'heart'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'admissions', 'variant' => 'promo-block', 'config' => [
                'badge' => 'Admissions Open', 'heading' => 'Join Our Community',
                'subtitle' => 'Now enrolling for the new academic session',
                'content' => 'Give your child the advantage of a disciplined, values-led education.',
                'key_dates' => [
                    ['date' => 'Jan', 'label' => 'Admission enquiry opens'],
                    ['date' => 'Mar', 'label' => 'Application deadline'],
                    ['date' => 'Apr', 'label' => 'New academic session begins'],
                ],
                'cta_label' => 'Submit enquiry', 'cta_url' => '/admission-enquiry',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'achievements', 'variant' => 'horizontal-scroll', 'config' => [
                'eyebrow' => 'Distinctions', 'heading' => 'Recent Achievements', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'board_results', 'variant' => 'stats-plus-toppers', 'config' => [
                'eyebrow' => 'Academic Record', 'heading' => 'Board Results',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'staff', 'variant' => 'card-grid', 'config' => [
                'eyebrow' => 'Meet the Team', 'heading' => 'Our Faculty', 'limit' => 12,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'house_system', 'variant' => 'color-cards', 'config' => [
                'heading' => 'Our House System',
                'houses' => [
                    ['name' => 'Crimson House', 'color' => '#DC2626', 'motto' => 'Courage in all things'],
                    ['name' => 'Azure House', 'color' => '#2563EB', 'motto' => 'Wisdom above all'],
                    ['name' => 'Emerald House', 'color' => '#16A34A', 'motto' => 'Growth through effort'],
                    ['name' => 'Amber House', 'color' => '#D97706', 'motto' => 'Unity in spirit'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'gallery', 'variant' => 'preview-grid', 'config' => [
                'eyebrow' => 'Life at {{name}}', 'heading' => 'Moments Beyond Learning', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'news', 'variant' => 'featured-strip', 'config' => [
                'heading' => 'News & Events',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'mandatory_disclosure', 'variant' => 'structured', 'config' => [
                'school_name' => '{{name}}',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'contact', 'variant' => 'centered-card', 'config' => [
                'badge' => 'Get In Touch', 'heading' => 'Contact the Office',
                'intro' => 'Have a question? Send us a message and we will get back to you.',
                'form_slug' => 'contact',
            ], 'layout' => ['width' => 'narrow', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'center']],
        ],
    ],

    'achievements-led' => [
        'name' => 'Achievements Led',
        'version' => '1.0.0',
        'purpose' => 'A bold, energetic site for schools that want to lead with their track record — results, sports and competitions.',
        'audience' => 'Parents comparing schools on results and extracurricular strength',
        'character' => 'Bold, energetic, achievement badges and stat-forward',
        'accent' => 'Warm red & gold, vibrant photography',
        'design' => [
            'primary' => '#B91C1C', 'secondary' => '#7F1D1D', 'accent_color' => '#FBBF24',
            'display_font' => 'Manrope', 'body_font' => 'Inter', 'type_scale' => 'balanced',
            'density' => 'comfortable', 'surface' => 'elevated', 'corners' => 'rounded',
            'buttons' => 'solid', 'images' => 'vibrant', 'motion' => 'expressive',
            'navigation' => 'dark', 'footer' => 'two-column-logo',
        ],
        'widgets' => ['news_ticker' => true, 'admission_banner' => true, 'social_strip' => true],
        'sections' => [
            ['section_type' => 'hero', 'variant' => 'full-slider', 'config' => [
                'slides' => [
                    ['title' => '{{name}}', 'subtitle' => 'CBSE Affiliated', 'description' => 'Where champions are made — in the classroom and beyond.', 'cta_label' => 'Admissions Open', 'cta_url' => '#admission'],
                ],
            ], 'layout' => ['width' => 'full', 'spacing' => 'compact', 'surface' => 'dark', 'heading_alignment' => 'left']],
            ['section_type' => 'achievements', 'variant' => 'horizontal-scroll', 'config' => [
                'eyebrow' => 'Celebrating Success', 'heading' => 'Recent Achievements', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'board_results', 'variant' => 'stats-plus-toppers', 'config' => [
                'eyebrow' => 'Proven Results', 'heading' => 'Board Results',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'about', 'variant' => 'overlap-stats', 'config' => [
                'eyebrow' => 'About Us', 'heading' => 'Building Champions, In and Out of Class',
                'body' => 'At {{name}}, academic rigor and competitive spirit go hand in hand.',
                'mini_stats' => [
                    ['value' => '2000+', 'label' => 'Students'],
                    ['value' => '80+', 'label' => 'Qualified Staff'],
                    ['value' => 'CBSE', 'label' => 'Affiliated'],
                ],
                'cta_label' => 'Know More', 'cta_url' => '#about',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'house_system', 'variant' => 'color-cards', 'config' => [
                'heading' => 'Our House System',
                'houses' => [
                    ['name' => 'Crimson House', 'color' => '#DC2626', 'motto' => 'Courage in all things'],
                    ['name' => 'Azure House', 'color' => '#2563EB', 'motto' => 'Wisdom above all'],
                    ['name' => 'Emerald House', 'color' => '#16A34A', 'motto' => 'Growth through effort'],
                    ['name' => 'Amber House', 'color' => '#D97706', 'motto' => 'Unity in spirit'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'facilities', 'variant' => 'why-choose-cards', 'config' => [
                'eyebrow' => 'Why Choose Us', 'heading_line1' => 'Built to Help Every Student', 'heading_line2' => 'Win',
                'items' => [
                    ['title' => 'Academic Excellence', 'description' => 'A strong, well-rounded curriculum.', 'icon' => 'education'],
                    ['title' => 'Sports & Co-Curricular', 'description' => 'Competitive teams and coaching across sports.', 'icon' => 'smile'],
                    ['title' => 'Modern Learning', 'description' => 'Facilities built for curiosity.', 'icon' => 'bulb'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'academic_programmes', 'variant' => 'cards', 'config' => [
                'heading' => 'Academic Programme',
                'streams' => [
                    ['icon' => '🎒', 'name' => 'Primary', 'description' => 'Foundational years focused on curiosity, literacy and numeracy.', 'subjects' => ['English', 'Maths', 'EVS', 'Art']],
                    ['icon' => '📚', 'name' => 'Middle School', 'description' => 'Building strong concepts across core subjects and skills.', 'subjects' => ['Science', 'Social Science', 'Maths', 'Languages']],
                    ['icon' => '🎓', 'name' => 'Secondary & Senior Secondary', 'description' => 'CBSE curriculum preparing students for board examinations and beyond.', 'subjects' => ['Science', 'Commerce', 'Humanities']],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'admissions', 'variant' => 'promo-block', 'config' => [
                'badge' => 'Admissions Open', 'heading' => 'Join the Team',
                'subtitle' => 'Now enrolling for the new academic session',
                'content' => 'Give your child the advantage of quality education and a competitive edge.',
                'key_dates' => [
                    ['date' => 'Jan', 'label' => 'Admission enquiry opens'],
                    ['date' => 'Mar', 'label' => 'Application deadline'],
                    ['date' => 'Apr', 'label' => 'New academic session begins'],
                ],
                'cta_label' => 'Submit enquiry', 'cta_url' => '/admission-enquiry',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'principal_message', 'variant' => 'split-portrait', 'config' => [
                'lead_in' => 'A Message from the Principal',
                'message' => 'Welcome to {{name}}. We believe every student has a champion inside them — our job is to help them find it, on the field and in the classroom.',
                'designation' => 'Principal',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'staff', 'variant' => 'card-grid', 'config' => [
                'eyebrow' => 'Meet the Team', 'heading' => 'Our Faculty & Coaches', 'limit' => 12,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'gallery', 'variant' => 'preview-grid', 'config' => [
                'eyebrow' => 'Life at {{name}}', 'heading' => 'Moments Beyond Learning', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'testimonials', 'variant' => 'card-grid', 'config' => [
                'heading' => 'What Parents Say',
                'testimonials' => [
                    ['name' => 'Parent, {{name}}', 'designation' => 'Class V Parent', 'quote' => 'Our child has grown so much in confidence since joining {{name}}. Coaches and teachers push every student to do their best.', 'rating' => 5],
                    ['name' => 'Parent, {{name}}', 'designation' => 'Class IX Parent', 'quote' => 'A nurturing environment with strong academics and a real competitive edge.', 'rating' => 5],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'news', 'variant' => 'featured-strip', 'config' => [
                'heading' => 'News & Events',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'left']],
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

    'academic-excellence' => [
        'name' => 'Academic Excellence',
        'version' => '1.0.0',
        'purpose' => 'A curriculum-forward, professional site for schools that lead with rigor, faculty quality and results.',
        'audience' => 'Parents prioritizing academic outcomes and teaching quality',
        'character' => 'Professional, structured, results-forward',
        'accent' => 'Blue & teal, documentary photography',
        'design' => [
            'primary' => '#1E40AF', 'secondary' => '#1E3A8A', 'accent_color' => '#F59E0B',
            'display_font' => 'Inter', 'body_font' => 'Inter', 'type_scale' => 'balanced',
            'density' => 'comfortable', 'surface' => 'soft', 'corners' => 'soft',
            'buttons' => 'solid', 'images' => 'documentary', 'motion' => 'restrained',
            'navigation' => 'logo-left', 'footer' => 'three-column',
        ],
        'widgets' => ['news_ticker' => false, 'admission_banner' => false, 'social_strip' => true],
        'sections' => [
            ['section_type' => 'hero', 'variant' => 'full-slider', 'config' => [
                'slides' => [
                    ['title' => '{{name}}', 'subtitle' => 'CBSE Affiliated', 'description' => 'Rigorous academics, dedicated faculty, proven results.', 'cta_label' => 'Admissions Open', 'cta_url' => '#admission'],
                ],
            ], 'layout' => ['width' => 'full', 'spacing' => 'compact', 'surface' => 'dark', 'heading_alignment' => 'left']],
            ['section_type' => 'about', 'variant' => 'overlap-stats', 'config' => [
                'eyebrow' => 'About Us', 'heading' => 'Academic Excellence, Every Year',
                'body' => 'At {{name}}, every decision starts with what helps students learn best.',
                'mini_stats' => [
                    ['value' => '2000+', 'label' => 'Students'],
                    ['value' => '80+', 'label' => 'Qualified Staff'],
                    ['value' => 'CBSE', 'label' => 'Affiliated'],
                ],
                'cta_label' => 'Know More', 'cta_url' => '#about',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'academic_programmes', 'variant' => 'cards', 'config' => [
                'heading' => 'Academic Programme',
                'streams' => [
                    ['icon' => '🎒', 'name' => 'Primary', 'description' => 'Foundational years focused on curiosity, literacy and numeracy.', 'subjects' => ['English', 'Maths', 'EVS', 'Art']],
                    ['icon' => '📚', 'name' => 'Middle School', 'description' => 'Building strong concepts across core subjects and skills.', 'subjects' => ['Science', 'Social Science', 'Maths', 'Languages']],
                    ['icon' => '🎓', 'name' => 'Secondary & Senior Secondary', 'description' => 'CBSE curriculum preparing students for board examinations and beyond.', 'subjects' => ['Science', 'Commerce', 'Humanities']],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'board_results', 'variant' => 'stats-plus-toppers', 'config' => [
                'eyebrow' => 'Proven Outcomes', 'heading' => 'Board Results',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'staff', 'variant' => 'card-grid', 'config' => [
                'eyebrow' => 'Our Faculty', 'heading' => 'Qualified, Dedicated Teachers', 'limit' => 12,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'facilities', 'variant' => 'why-choose-cards', 'config' => [
                'eyebrow' => 'Why Choose Us', 'heading_line1' => 'Why Families Choose', 'heading_line2' => '{{name}}',
                'items' => [
                    ['title' => 'Academic Excellence', 'description' => 'A strong, well-rounded curriculum.', 'icon' => 'education'],
                    ['title' => 'Safe Campus', 'description' => 'A secure, caring environment.', 'icon' => 'shield'],
                    ['title' => 'Modern Learning', 'description' => 'Facilities built for curiosity.', 'icon' => 'bulb'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'admissions', 'variant' => 'promo-block', 'config' => [
                'badge' => 'Admissions Open', 'heading' => 'Join Our Community',
                'subtitle' => 'Now enrolling for the new academic session',
                'content' => 'Give your child the advantage of quality education and a nurturing environment.',
                'key_dates' => [
                    ['date' => 'Jan', 'label' => 'Admission enquiry opens'],
                    ['date' => 'Mar', 'label' => 'Application deadline'],
                    ['date' => 'Apr', 'label' => 'New academic session begins'],
                ],
                'cta_label' => 'Submit enquiry', 'cta_url' => '/admission-enquiry',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'principal_message', 'variant' => 'split-portrait', 'config' => [
                'lead_in' => 'A Message from the Principal',
                'message' => 'Welcome to {{name}}. We hold ourselves to a high standard — for our students, our teachers and our results — because that is what every family deserves.',
                'designation' => 'Principal',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'achievements', 'variant' => 'horizontal-scroll', 'config' => [
                'eyebrow' => 'Celebrating Success', 'heading' => 'Recent Achievements', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'house_system', 'variant' => 'color-cards', 'config' => [
                'heading' => 'Our House System',
                'houses' => [
                    ['name' => 'Crimson House', 'color' => '#DC2626', 'motto' => 'Courage in all things'],
                    ['name' => 'Azure House', 'color' => '#2563EB', 'motto' => 'Wisdom above all'],
                    ['name' => 'Emerald House', 'color' => '#16A34A', 'motto' => 'Growth through effort'],
                    ['name' => 'Amber House', 'color' => '#D97706', 'motto' => 'Unity in spirit'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'gallery', 'variant' => 'preview-grid', 'config' => [
                'eyebrow' => 'Life at {{name}}', 'heading' => 'Moments Beyond Learning', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'testimonials', 'variant' => 'card-grid', 'config' => [
                'heading' => 'What Parents Say',
                'testimonials' => [
                    ['name' => 'Parent, {{name}}', 'designation' => 'Class V Parent', 'quote' => 'Our child has grown so much in confidence and curiosity since joining {{name}}. The teachers genuinely care.', 'rating' => 5],
                    ['name' => 'Parent, {{name}}', 'designation' => 'Class IX Parent', 'quote' => 'A nurturing environment with strong academics and great extracurricular exposure.', 'rating' => 5],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'center']],
            ['section_type' => 'news', 'variant' => 'featured-strip', 'config' => [
                'heading' => 'News & Events',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'left']],
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

    'compact-community' => [
        'name' => 'Compact Community',
        'version' => '1.0.0',
        'purpose' => 'A simple, warm single-scroll site for smaller or independent schools that just need the essentials done well.',
        'audience' => 'Parents of a small or newly-established school, especially independent (non-Sahodaya) schools',
        'character' => 'Warm, uncomplicated, fewer sections, friendly tone',
        'accent' => 'Friendly green, documentary photography',
        'design' => [
            'primary' => '#16A34A', 'secondary' => '#15803D', 'accent_color' => '#F59E0B',
            'display_font' => 'Inter', 'body_font' => 'Inter', 'type_scale' => 'balanced',
            'density' => 'spacious', 'surface' => 'elevated', 'corners' => 'rounded',
            'buttons' => 'solid', 'images' => 'documentary', 'motion' => 'restrained',
            'navigation' => 'centered-below', 'footer' => 'minimal',
        ],
        'widgets' => ['news_ticker' => false, 'admission_banner' => false, 'social_strip' => true],
        'sections' => [
            ['section_type' => 'hero', 'variant' => 'centered', 'config' => [
                'heading' => '{{name}}', 'tagline' => 'A warm, welcoming place to learn and grow.',
                'cta_label' => 'Admissions Open', 'cta_url' => '#admissions',
            ], 'layout' => ['width' => 'full', 'spacing' => 'standard', 'surface' => 'dark', 'heading_alignment' => 'center']],
            ['section_type' => 'about', 'variant' => 'overlap-stats', 'config' => [
                'eyebrow' => 'About Us', 'heading' => 'Building Knowledge, Values, and Confidence',
                'body' => 'Welcome to {{name}} — a close-knit school community where every child is known and encouraged.',
                'mini_stats' => [
                    ['value' => 'CBSE', 'label' => 'Affiliated'],
                ],
                'cta_label' => 'Know More', 'cta_url' => '#about',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'facilities', 'variant' => 'why-choose-cards', 'config' => [
                'eyebrow' => 'Why Choose Us', 'heading_line1' => 'Why Families Choose', 'heading_line2' => '{{name}}',
                'items' => [
                    ['title' => 'Small Class Sizes', 'description' => 'Every student gets real attention.', 'icon' => 'smile'],
                    ['title' => 'Safe Campus', 'description' => 'A secure, caring environment.', 'icon' => 'shield'],
                    ['title' => 'Caring Teachers', 'description' => 'Teachers who know each student by name.', 'icon' => 'heart'],
                ],
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'admissions', 'variant' => 'promo-block', 'config' => [
                'badge' => 'Admissions Open', 'heading' => 'Join Our Community',
                'subtitle' => 'Now enrolling for the new academic session',
                'content' => 'Give your child the advantage of quality education and a nurturing environment.',
                'cta_label' => 'Submit enquiry', 'cta_url' => '/admission-enquiry',
            ], 'layout' => ['width' => 'wide', 'spacing' => 'spacious', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'principal_message', 'variant' => 'split-portrait', 'config' => [
                'lead_in' => 'A Message from the Principal',
                'message' => 'Welcome to {{name}}. Ours is a small community by design — small enough that every child is known, encouraged, and given room to grow.',
                'designation' => 'Principal',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'left']],
            ['section_type' => 'gallery', 'variant' => 'preview-grid', 'config' => [
                'eyebrow' => 'Life at {{name}}', 'heading' => 'Moments Beyond Learning', 'limit' => 8,
            ], 'layout' => ['width' => 'wide', 'spacing' => 'standard', 'surface' => 'canvas', 'heading_alignment' => 'left']],
            ['section_type' => 'mandatory_disclosure', 'variant' => 'structured', 'config' => [
                'school_name' => '{{name}}',
            ], 'layout' => ['width' => 'standard', 'spacing' => 'standard', 'surface' => 'muted', 'heading_alignment' => 'center']],
            ['section_type' => 'contact', 'variant' => 'centered-card', 'config' => [
                'badge' => 'Get In Touch', 'heading' => 'Contact Us',
                'intro' => 'Have a question? Send us a message and we will get back to you.',
                'form_slug' => 'contact',
            ], 'layout' => ['width' => 'narrow', 'spacing' => 'spacious', 'surface' => 'muted', 'heading_alignment' => 'center']],
        ],
    ],
];
