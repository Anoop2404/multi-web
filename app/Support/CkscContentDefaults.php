<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Default content from the standalone CKSC Sahodaya website (/Users/neoo/laravel/sahodaya).
 * Used when no production DB export is available.
 */
class CkscContentDefaults
{
    public const ORG_TITLE = 'CONFEDERATION OF KERALA SAHODAYA COMPLEXES';

    public const CONTACT = [
        'address' => 'Sree Sarada Vidyalaya, Kalady, Ernakulam',
        'phone'   => '+91 9447154684',
        'email'   => 'confederationkerala@gmail.com',
    ];

    /** @return array<string, mixed> */
    public static function navConfig(): array
    {
        return [
            'style'          => 'cksc-pill',
            'layout_variant' => 'cksc-pill',
            'items'          => [
                ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
                ['label' => 'About Us', 'url' => '/about', 'external' => false, 'children' => []],
                ['label' => 'Executive Committee', 'url' => '/executive', 'external' => false, 'children' => []],
                [
                    'label' => 'Gallery', 'url' => '#', 'external' => false,
                    'children' => [
                        ['label' => 'Function Gallery', 'url' => '/gallery/function', 'external' => false],
                        ['label' => 'Programme Gallery', 'url' => '/gallery/programme', 'external' => false],
                        ['label' => 'Sahodaya Gallery', 'url' => '/gallery/sahodya', 'external' => false],
                    ],
                ],
                [
                    'label' => 'MOA', 'url' => '#', 'external' => false,
                    'children' => [
                        ['label' => 'Structure', 'url' => '/moa/structure', 'external' => false],
                        ['label' => 'Rules and Bye-laws', 'url' => '/moa/rules', 'external' => false],
                        ['label' => 'Meetings', 'url' => '/moa/meetings', 'external' => false],
                        ['label' => 'Authority', 'url' => '/moa/authority', 'external' => false],
                        ['label' => 'Activities', 'url' => '/moa/activities', 'external' => false],
                        ['label' => 'Election', 'url' => '/moa/election', 'external' => false],
                    ],
                ],
                ['label' => 'Downloads', 'url' => '/downloads', 'external' => false, 'children' => []],
                [
                    'label' => 'Programmes', 'url' => '/#programmes', 'external' => false,
                    'children' => [
                        ['label' => 'Athletic Meet', 'url' => '/#programmes', 'external' => false],
                        ['label' => 'Kalotsav', 'url' => '/fest', 'external' => false],
                        ['label' => 'Writings', 'url' => '/#programmes', 'external' => false],
                        ['label' => 'Language Fest', 'url' => '/#programmes', 'external' => false],
                        ['label' => 'Teacher Fest', 'url' => '/#programmes', 'external' => false],
                        ['label' => 'Membership Renewal', 'url' => '/school-register', 'external' => false],
                    ],
                ],
                ['label' => 'Contact Us', 'url' => '/contact', 'external' => false, 'children' => []],
            ],
            'portal_cta' => array_merge(PortalNavLinks::portalCtaDefaults(), [
                'show_in_navbar' => true,
                'show_in_menu'   => false,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public static function theme(): array
    {
        return [
            'primary'        => '#15224D',
            'secondary'      => '#D9AF4B',
            'accent_color'   => '#D9AF4B',
            'font_heading'   => 'Roboto',
            'font_body'      => 'Roboto',
            'border_radius'  => '0.75rem',
            'navbar_style'   => 'light',
            'footer_style'   => 'dark',
        ];
    }

    /** @return array<string, mixed> */
    public static function footerConfig(Tenant $tenant): array
    {
        return [
            'layout_variant' => 'three-column',
            'tagline'        => self::ORG_TITLE,
            'copyright'      => '© '.date('Y').' CKSC. All rights reserved.',
            'phone'          => self::CONTACT['phone'],
            'email'          => self::CONTACT['email'],
            'address'        => self::CONTACT['address'],
            'quick_links'    => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'About Us', 'url' => '/about'],
                ['label' => 'Executive Committee', 'url' => '/executive'],
                ['label' => 'Contact Us', 'url' => '/contact'],
                ['label' => 'School Registration', 'url' => PortalNavLinks::REGISTER_URL],
            ],
            'programme_links' => [
                ['label' => 'Athletic Meet', 'url' => '/#programmes'],
                ['label' => 'Kalotsav', 'url' => '/fest'],
                ['label' => 'Writings', 'url' => '/#programmes'],
                ['label' => 'Language Fest', 'url' => '/#programmes'],
                ['label' => 'Teacher Fest', 'url' => '/#programmes'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function homepageSections(Tenant $tenant): array
    {
        return [
            [
                'section_type'  => 'hero',
                'variant'       => 'cksc-slider',
                'display_order' => 1,
                'config'        => [
                    'slides' => [
                        [
                            'title'   => $tenant->name,
                            'content' => 'Uniting CBSE Sahodaya complexes across Kerala for academic excellence, cultural programmes, and collaborative growth.',
                            'image'   => null,
                        ],
                        [
                            'title'   => 'Caring and Sharing',
                            'content' => 'Promoting academic excellence, sports, cultural activities, and professional development among CBSE affiliated schools.',
                            'image'   => null,
                        ],
                    ],
                    'autoplay_seconds' => 5,
                ],
            ],
            [
                'section_type'  => 'about_sahodaya',
                'variant'       => 'single-column',
                'display_order' => 2,
                'config'        => [
                    'heading' => 'About the Confederation',
                    'content' => "The Confederation of Kerala Sahodaya Complexes (CKSC) is an association of Sahodaya School Complexes in Kerala, fostering collaboration among CBSE-affiliated schools through academic programmes, sports meets, cultural festivals, and teacher development initiatives.\n\nGuided by the Sahodaya philosophy of Caring and Sharing, we work together to create meaningful opportunities for students, teachers, and member schools across the state.",
                    'eyebrow' => 'About Us',
                ],
            ],
            [
                'section_type'  => 'about_sahodaya',
                'variant'       => 'vision-mission',
                'display_order' => 3,
                'config'        => [
                    'heading'         => 'Our Purpose',
                    'vision_heading'  => 'Vision',
                    'vision'          => 'Fostering excellence in education by creating a collaborative network of CBSE Sahodaya schools across Kerala.',
                    'mission_heading' => 'Mission',
                    'mission'         => 'To promote academic excellence, sports, cultural activities, and professional development among CBSE affiliated schools through shared programmes and collective growth.',
                    'motto'           => 'Caring and Sharing',
                    'values'          => [
                        'Collaboration among member Sahodaya complexes',
                        'Academic and co-curricular excellence',
                        'Teacher empowerment and professional development',
                        'Cultural diversity and national integration',
                    ],
                ],
            ],
            [
                'section_type'  => 'programmes',
                'variant'       => 'service-grid',
                'display_order' => 4,
                'config'        => [
                    'heading'    => 'Services',
                    'eyebrow'    => 'Programmes',
                    'programmes' => [
                        ['label' => 'Kalotsav', 'description' => 'State-level cultural festival for CBSE schools.', 'icon' => '🎭', 'url' => '/fest'],
                        ['label' => 'Athletic Meet', 'description' => 'Sports competitions fostering teamwork and fitness.', 'icon' => '🏃', 'url' => '/#programmes'],
                        ['label' => 'Teacher Fest', 'description' => 'Professional development and recognition for educators.', 'icon' => '👩‍🏫', 'url' => '/#programmes'],
                        ['label' => 'Language Fest', 'description' => 'Celebrating linguistic diversity and communication skills.', 'icon' => '📚', 'url' => '/#programmes'],
                        ['label' => 'Writings', 'description' => 'Literary competitions nurturing creative expression.', 'icon' => '✍️', 'url' => '/#programmes'],
                        ['label' => 'Membership Renewal', 'description' => 'Annual registration for member schools.', 'icon' => '🏫', 'url' => '/school-register'],
                    ],
                ],
            ],
            [
                'section_type'  => 'about_sahodaya',
                'variant'       => 'with-timeline',
                'display_order' => 5,
                'config'        => [
                    'heading'    => 'Our path of creating meaningful change',
                    'eyebrow'    => 'Journey',
                    'subheading' => 'A chronicle of dedication, impact, and continuous growth.',
                    'milestones' => [
                        ['year' => '2007', 'title' => 'CBSE Recognition', 'description' => 'Formal guidelines for Sahodaya complexes established under CBSE circular EO(H&L)/SSC/07.'],
                        ['year' => '2010', 'title' => 'State-wide Collaboration', 'description' => 'Member complexes across Kerala begin coordinated academic and cultural programmes.'],
                        ['year' => '2015', 'title' => 'Kalotsav Growth', 'description' => 'State Kalotsav becomes flagship cultural event for member schools.'],
                        ['year' => '2020', 'title' => 'Digital Transformation', 'description' => 'Online registration, results, and communication platforms adopted.'],
                        ['year' => '2024', 'title' => 'Sahodaya Connect', 'description' => 'Unified digital platform for membership, events, and school coordination.'],
                    ],
                ],
            ],
            [
                'section_type'  => 'gallery',
                'variant'       => 'masonry-grid',
                'display_order' => 6,
                'config'        => [
                    'heading' => 'Gallery',
                    'eyebrow' => 'Highlights',
                ],
            ],
            [
                'section_type'  => 'office_bearers',
                'variant'       => 'photo-cards',
                'display_order' => 7,
                'config'        => ['heading' => 'Office Bearers', 'eyebrow' => 'Leadership'],
            ],
            [
                'section_type'  => 'testimonials_sahodaya',
                'variant'       => 'principal-quotes',
                'display_order' => 8,
                'config'        => [
                    'heading'       => 'Voices of Impact',
                    'quotes'        => [
                        ['name' => 'Principal, Member School', 'school' => 'CBSE Sahodaya School', 'text' => 'The Confederation has been instrumental in providing our students with platforms to showcase talent beyond the classroom.'],
                        ['name' => 'Teacher Coordinator', 'school' => 'Member Sahodaya', 'text' => 'Collaborative programmes through CKSC have enriched professional development for teachers across complexes.'],
                        ['name' => 'School Administrator', 'school' => 'Kerala CBSE School', 'text' => 'Membership in the Sahodaya network opens doors to state-level competitions and shared resources.'],
                    ],
                ],
            ],
            [
                'section_type'  => 'news_circulars',
                'variant'       => 'grid',
                'display_order' => 9,
                'config'        => [
                    'heading'    => 'News & Events',
                    'subheading' => 'Stay updated with the latest happenings, conferences, and activities organized by the Confederation of Kerala Sahodaya Complexes.',
                ],
            ],
            [
                'section_type'  => 'contact',
                'variant'       => 'stacked',
                'display_order' => 10,
                'config'        => [
                    'heading' => 'Contact Us',
                    'address' => self::CONTACT['address'],
                    'phone'   => self::CONTACT['phone'],
                    'email'   => self::CONTACT['email'],
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function cmsPages(): array
    {
        return [
            'about' => [
                'title'       => 'About Us',
                'subtitle'    => 'About Us',
                'content_html' => '<p>The Confederation of Kerala Sahodaya Complexes (CKSC) is an association of the Sahodaya School Complexes in the state of Kerala. All guidelines and norms for formation and functioning of Sahodaya School Complexes under CBSE provisions apply to the Confederation.</p><p>We hold training programmes, lectures, conferences, seminars, exhibitions, camps and competitions on academic and co-curricular activities for students of CBSE schools across Kerala.</p>',
            ],
            'executive' => [
                'title'          => 'Executive Committee',
                'subtitle'       => 'Executive Committee',
                'table_headers'  => ['#', 'Member Name', 'Sahodaya Complex'],
                'table_rows'     => self::executiveCommitteeRows(),
            ],
            'contact' => [
                'title'    => 'Contact Us',
                'subtitle' => 'Contact Us',
                'content_html' => '<p><strong>Address:</strong> '.self::CONTACT['address'].'</p><p><strong>Phone:</strong> '.self::CONTACT['phone'].'</p><p><strong>Email:</strong> '.self::CONTACT['email'].'</p><p><strong>Working Hours:</strong> Mon–Fri 09:30–15:30, Sat 10:00–12:00, Sun Closed</p>',
            ],
            'downloads' => [
                'title'    => 'Downloads',
                'subtitle' => 'Downloads',
                'content_html' => '<p>Access important files, circulars, results, and documents from the Confederation. Downloads are managed from the Sahodaya admin panel.</p>',
            ],
            'moa/structure' => [
                'title'    => 'Structure',
                'subtitle' => 'STRUCTURE',
                'content_html' => self::structureContent(),
            ],
            'moa/rules' => [
                'title'    => 'Rules and Bye-laws',
                'subtitle' => 'Rules and Bye-laws',
                'content_html' => '<p>Rules and bye-laws governing the Confederation of Kerala Sahodaya Complexes. Content migrated from the standalone website — edit in Site Builder CMS pages.</p>',
            ],
            'moa/meetings' => [
                'title'    => 'Meetings',
                'subtitle' => 'Meetings',
                'content_html' => '<p>Information about general body meetings, executive meetings, and annual conventions of the Confederation.</p>',
            ],
            'moa/authority' => [
                'title'    => 'Authority',
                'subtitle' => 'AUTHORITY',
                'content_html' => '<p>Powers and authority delegated to office bearers and executive committee as per the MOA.</p>',
            ],
            'moa/activities' => [
                'title'    => 'Activities',
                'subtitle' => 'Activities',
                'content_html' => '<p>Annual activities, programmes, and initiatives undertaken by the Confederation across member Sahodaya complexes.</p>',
            ],
            'moa/election' => [
                'title'    => 'Election',
                'subtitle' => 'Election',
                'content_html' => '<p>Election procedures and schedules for office bearers of the Confederation.</p>',
            ],
            'gallery/function' => [
                'title'    => 'Function Gallery',
                'subtitle' => 'Function Gallery',
                'content_html' => '<p>Photo gallery from official functions and ceremonies. Upload images via Sahodaya admin Public Content.</p>',
            ],
            'gallery/programme' => [
                'title'    => 'Programme Gallery',
                'subtitle' => 'Programme Gallery',
                'content_html' => '<p>Photos from Kalotsav, sports meets, and other programmes. Upload images via Sahodaya admin.</p>',
            ],
            'gallery/sahodya' => [
                'title'    => 'Sahodaya Gallery',
                'subtitle' => 'Sahodaya Gallery',
                'content_html' => '<p>Gallery highlighting Sahodaya complex activities across Kerala.</p>',
            ],
        ];
    }

    /** @return list<list<string>> */
    public static function executiveCommitteeRows(): array
    {
        return [
            ['1', 'Rev. Dr Sijan Paul Unnukallel', 'Central Kerala'],
            ['2', 'Mr. Shibu S', 'Bharat'],
            ['3', 'Mr. Benny George', 'Kottayam'],
            ['4', 'Mr. Jouhar M', 'Malapuram'],
            ['5', 'Mr. Subair K P', 'Kannur'],
            ['6', 'Mr. Shaji K Thayyil', 'Palakkad'],
            ['7', 'Mr. Babu Koikkara', 'Thrissur'],
            ['8', 'Fr. Karikkal Vincent Chacko', 'Kollam'],
            ['9', 'Dr. Abdul Jaleel P', 'Chandragiri'],
            ['10', 'Mr. Moni Yohannan', 'Pathanamthitta'],
            ['11', 'Mr. Rajesh Kumar R', 'Ernakulam'],
            ['12', 'Mr. Varghese P T', 'Idukki'],
            ['13', 'Mr. Anil Kumar', 'Alappuzha'],
            ['14', 'Mr. Jose Mathew', 'Trivandrum North'],
            ['15', 'Mr. Saji Thomas', 'Trivandrum South'],
            ['16', 'Mr. Raju M', 'Kasaragod'],
            ['17', 'Mr. Pradeep Kumar', 'Wayanad'],
            ['18', 'Mr. Suresh Babu', 'Calicut'],
            ['19', 'Mr. Abdul Samad', 'Malappuram Central'],
            ['20', 'Mr. Thomas Mathew', 'Cochin'],
            ['21', 'Mr. George Joseph', 'High Range'],
            ['22', 'Mr. Vijayan P', 'North Malabar'],
            ['23', 'Mr. Ramesh Babu', 'South Kerala'],
            ['24', 'Mr. Mohanan K', 'Central Travancore'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function stateMoaPages(): array
    {
        return [
            'moa/structure' => [
                'title'        => 'Structure',
                'subtitle'     => 'STRUCTURE',
                'content_html' => self::structureContent(),
            ],
            'moa/rules' => [
                'title'        => 'Rules and Bye-laws',
                'subtitle'     => 'Rules and Bye-laws',
                'content_html' => '<p>Rules and bye-laws governing the Confederation of Kerala Sahodaya Complexes.</p>',
            ],
            'moa/meetings' => [
                'title'        => 'Meetings',
                'subtitle'     => 'Meetings',
                'content_html' => '<p>Information about general body meetings, executive meetings, and annual conventions.</p>',
            ],
            'moa/authority' => [
                'title'        => 'Authority',
                'subtitle'     => 'AUTHORITY',
                'content_html' => '<p>Powers and authority delegated to office bearers and executive committee as per the MOA.</p>',
            ],
            'moa/activities' => [
                'title'        => 'Activities',
                'subtitle'     => 'Activities',
                'content_html' => '<p>Annual activities, programmes, and initiatives undertaken across member Sahodaya complexes.</p>',
            ],
            'moa/election' => [
                'title'        => 'Election',
                'subtitle'     => 'Election',
                'content_html' => '<p>Election procedures and schedules for office bearers.</p>',
            ],
        ];
    }

    private static function structureContent(): string
    {
        return <<<'HTML'
<p>The Confederation of Kerala Sahodaya Complexes shall be an association of the Sahodaya School Complexes in the state of Kerala. All the guidelines and norms with regard to the formation and functioning of Sahodaya School Complexes, under the provisions given by the Central Board of Secondary Education, shall be applicable to the Confederation of Kerala Sahodaya Complexes.</p>
<p><strong>1. Name:</strong> Confederation of Kerala Sahodaya Complexes (herein after called the 'Confederation')</p>
<p><strong>2. Office:</strong> Benchmark International School, Tirur, Kerala - 676101</p>
<p><strong>3. Address:</strong> Same as above</p>
<p><strong>4. Area of operation:</strong> All over Kerala State</p>
<p><strong>5. Aims and objectives:</strong> The objectives for which the association is established include:</p>
<ul>
<li>To hold and conduct training programs, lectures, conferences, seminars, exhibitions, camps and competitions on academic and co-curricular activities.</li>
<li>To give scholarships, certificates and awards to participants.</li>
<li>To conduct educational, vocational and career guidance and counseling programmes.</li>
<li>To print and publish journals, periodicals, books and other materials promoting education and national integrity.</li>
<li>To collaborate with state, national and other organizations with similar objectives.</li>
</ul>
HTML;
    }
}
