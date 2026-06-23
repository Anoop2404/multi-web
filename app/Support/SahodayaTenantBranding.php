<?php

namespace App\Support;

use App\Models\OfficeBearers;
use App\Models\SahodayaProfile;
use App\Models\Tenant;

/**
 * Per-tenant branding: names, contact, theme palette, and personalized copy.
 */
class SahodayaTenantBranding
{
    /** @var array<string, array<string, string>> */
    private const THEME_PRESETS = [
        'malappuram' => [
            'primary'       => '#1e40af',
            'secondary'     => '#7c3aed',
            'accent_color'  => '#f59e0b',
            'font_heading'  => 'Inter',
            'font_body'     => 'Inter',
        ],
        'travancore' => [
            'primary'       => '#0f766e',
            'secondary'     => '#14b8a6',
            'accent_color'  => '#f59e0b',
            'font_heading'  => 'Inter',
            'font_body'     => 'Inter',
        ],
        'kochi' => [
            'primary'       => '#1d4ed8',
            'secondary'     => '#3b82f6',
            'accent_color'  => '#eab308',
            'font_heading'  => 'Inter',
            'font_body'     => 'Inter',
        ],
        'cksc' => [
            'primary'       => '#15224D',
            'secondary'     => '#D9AF4B',
            'accent_color'  => '#D9AF4B',
            'font_heading'  => 'Roboto',
            'font_body'     => 'Roboto',
        ],
    ];

    /** @return array<string, mixed> */
    public static function context(Tenant $tenant): array
    {
        $profile = self::profile($tenant);
        $footer  = $tenant->getSetting('footer_config', []) ?? [];
        $region  = $profile?->cbse_region ?: self::regionLabel($tenant);
        $name    = $tenant->name;
        $short   = self::shortName($tenant, $region);

        return [
            'name'        => $name,
            'short_name'  => $short,
            'org_title'   => strtoupper($name),
            'region'      => $region,
            'tagline'     => self::tagline($tenant, $region),
            'motto'       => 'Caring and Sharing',
            'logo'        => $tenant->getSetting('logo'),
            'phone'       => $profile?->contact_phone ?: ($footer['phone'] ?? null),
            'email'       => $profile?->contact_email ?: ($footer['email'] ?? null),
            'address'     => $profile?->address ?: ($footer['address'] ?? null),
            'is_state'    => self::isStateLevel($tenant),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function themePresets(): array
    {
        return [
            ['id' => 'malappuram', 'label' => 'Malappuram Blue', 'primary' => '#1e40af', 'secondary' => '#7c3aed', 'accent_color' => '#f59e0b'],
            ['id' => 'sahodaya', 'label' => 'Sahodaya Purple', 'primary' => '#5b21b6', 'secondary' => '#7c3aed', 'accent_color' => '#f59e0b'],
            ['id' => 'teal', 'label' => 'Teal Green', 'primary' => '#0f766e', 'secondary' => '#14b8a6', 'accent_color' => '#f59e0b'],
            ['id' => 'royal', 'label' => 'Royal Blue', 'primary' => '#1d4ed8', 'secondary' => '#3b82f6', 'accent_color' => '#eab308'],
            ['id' => 'crimson', 'label' => 'Crimson Red', 'primary' => '#991b1b', 'secondary' => '#dc2626', 'accent_color' => '#f59e0b'],
            ['id' => 'forest', 'label' => 'Forest Green', 'primary' => '#166534', 'secondary' => '#22c55e', 'accent_color' => '#fbbf24'],
            ['id' => 'cksc', 'label' => 'Navy & Gold', 'primary' => '#15224D', 'secondary' => '#D9AF4B', 'accent_color' => '#D9AF4B'],
            ['id' => 'slate', 'label' => 'Modern Slate', 'primary' => '#1e293b', 'secondary' => '#475569', 'accent_color' => '#38bdf8'],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultTheme(Tenant $tenant): array
    {
        $base = self::THEME_PRESETS[$tenant->subdomain ?? '']
            ?? [
                'primary'      => '#5b21b6',
                'secondary'    => '#7c3aed',
                'accent_color' => '#f59e0b',
                'font_heading' => 'Inter',
                'font_body'    => 'Inter',
            ];

        return array_merge([
            'border_radius' => '0.75rem',
            'navbar_style'  => 'light',
            'footer_style'  => 'dark',
            'customized'    => false,
        ], $base);
    }

    /** @return array<string, mixed> */
    public static function theme(Tenant $tenant, bool $preserveCustom = true): array
    {
        $existing = $tenant->getSetting('theme', []) ?? [];

        if ($preserveCustom && ! empty($existing['customized'])) {
            return array_merge(self::defaultTheme($tenant), $existing);
        }

        if ($preserveCustom && ! empty($existing['primary'])) {
            return array_merge(self::defaultTheme($tenant), $existing);
        }

        return self::defaultTheme($tenant);
    }

    /** @param array<string, mixed> $colors */
    public static function saveTheme(Tenant $tenant, array $colors): array
    {
        $theme = array_merge(self::defaultTheme($tenant), [
            'primary'       => $colors['primary'],
            'secondary'     => $colors['secondary'],
            'accent_color'  => $colors['accent_color'] ?? $colors['accent'] ?? '#f59e0b',
            'font_heading'  => $colors['font_heading'] ?? 'Inter',
            'font_body'     => $colors['font_body'] ?? 'Inter',
            'border_radius' => $colors['border_radius'] ?? '0.75rem',
            'navbar_style'  => $colors['navbar_style'] ?? 'light',
            'footer_style'  => $colors['footer_style'] ?? 'dark',
            'customized'    => true,
        ]);

        $tenant->setSetting('theme', $theme);

        return $theme;
    }

    /** @return array<string, mixed> */
    public static function navConfig(Tenant $tenant): array
    {
        $ctx = self::context($tenant);

        $items = [
            ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
            ['label' => 'About Us', 'url' => '/about', 'external' => false, 'children' => []],
        ];

        if ($ctx['is_state']) {
            $items[] = ['label' => 'Executive Committee', 'url' => '/executive', 'external' => false, 'children' => []];
        } else {
            $items[] = ['label' => 'Office Bearers', 'url' => '/#office-bearers', 'external' => false, 'children' => []];
            $items[] = ['label' => 'Member Schools', 'url' => '/#member-schools', 'external' => false, 'children' => []];
        }

        $items[] = [
            'label' => 'Gallery', 'url' => '#', 'external' => false,
            'children' => [
                ['label' => 'Function Gallery', 'url' => '/gallery/function', 'external' => false],
                ['label' => 'Programme Gallery', 'url' => '/gallery/programme', 'external' => false],
                ['label' => 'Sahodaya Gallery', 'url' => '/gallery/sahodya', 'external' => false],
            ],
        ];

        if ($ctx['is_state']) {
            $items[] = [
                'label' => 'MOA', 'url' => '#', 'external' => false,
                'children' => [
                    ['label' => 'Structure', 'url' => '/moa/structure', 'external' => false],
                    ['label' => 'Rules and Bye-laws', 'url' => '/moa/rules', 'external' => false],
                    ['label' => 'Meetings', 'url' => '/moa/meetings', 'external' => false],
                    ['label' => 'Authority', 'url' => '/moa/authority', 'external' => false],
                    ['label' => 'Activities', 'url' => '/moa/activities', 'external' => false],
                    ['label' => 'Election', 'url' => '/moa/election', 'external' => false],
                ],
            ];
        }

        $items[] = ['label' => 'Downloads', 'url' => '/downloads', 'external' => false, 'children' => []];
        $items[] = [
            'label' => 'Programmes', 'url' => '/#programmes', 'external' => false,
            'children' => [
                ['label' => 'Athletic Meet', 'url' => '/#programmes', 'external' => false],
                ['label' => 'Kalotsav', 'url' => '/fest', 'external' => false],
                ['label' => 'Kids Fest', 'url' => '/#programmes', 'external' => false],
                ['label' => 'Teacher Fest', 'url' => '/#programmes', 'external' => false],
                ['label' => 'Membership Renewal', 'url' => '/school-register', 'external' => false],
            ],
        ];
        $items[] = ['label' => 'Contact Us', 'url' => '/contact', 'external' => false, 'children' => []];

        return [
            'style'          => 'cksc-pill',
            'layout_variant' => 'cksc-pill',
            'items'          => $items,
            'portal_cta'     => array_merge(PortalNavLinks::portalCtaDefaults(), [
                'show_in_navbar' => true,
                'show_in_menu'   => false,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public static function footerConfig(Tenant $tenant): array
    {
        $ctx = self::context($tenant);
        $year = date('Y');

        return [
            'layout_variant'  => 'three-column',
            'tagline'         => $ctx['org_title'],
            'copyright'       => "© {$year} {$ctx['name']}. All rights reserved.",
            'phone'           => $ctx['phone'],
            'email'           => $ctx['email'],
            'address'         => $ctx['address'],
            'quick_links'     => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'About Us', 'url' => '/about'],
                ['label' => $ctx['is_state'] ? 'Executive Committee' : 'Office Bearers', 'url' => $ctx['is_state'] ? '/executive' : '/#office-bearers'],
                ['label' => 'Contact Us', 'url' => '/contact'],
                ['label' => 'School Registration', 'url' => PortalNavLinks::REGISTER_URL],
            ],
            'programme_links' => [
                ['label' => 'Kalotsav', 'url' => '/fest'],
                ['label' => 'Athletic Meet', 'url' => '/#programmes'],
                ['label' => 'Kids Fest', 'url' => '/#programmes'],
                ['label' => 'Teacher Fest', 'url' => '/#programmes'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function homepageSections(Tenant $tenant): array
    {
        $ctx = self::context($tenant);

        return [
            [
                'section_type'  => 'hero',
                'variant'       => 'cksc-slider',
                'display_order' => 1,
                'config'        => [
                    'logo'   => $ctx['logo'],
                    'slides' => [
                        [
                            'title'   => $ctx['name'],
                            'content' => $ctx['tagline'],
                            'image'   => null,
                        ],
                        [
                            'title'   => $ctx['motto'],
                            'content' => 'Promoting academic excellence, sports, cultural activities, and professional development among CBSE affiliated schools in '.$ctx['region'].'.',
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
                    'heading' => 'About '.$ctx['short_name'],
                    'content' => self::aboutText($tenant, $ctx),
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
                    'vision'          => 'Fostering excellence in education through collaboration among CBSE Sahodaya member schools in '.$ctx['region'].'.',
                    'mission_heading' => 'Mission',
                    'mission'         => 'To promote academic excellence, sports, cultural activities, and professional development among CBSE affiliated schools through shared programmes and collective growth.',
                    'motto'           => $ctx['motto'],
                    'values'          => [
                        'Collaboration among member schools',
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
                    'heading'    => 'Programmes & Services',
                    'eyebrow'    => 'What We Do',
                    'programmes' => SahodayaPublicData::programmes([]),
                ],
            ],
            [
                'section_type'  => 'about_sahodaya',
                'variant'       => 'with-timeline',
                'display_order' => 5,
                'config'        => [
                    'heading'    => 'Our journey in '.$ctx['region'],
                    'eyebrow'    => 'Journey',
                    'subheading' => 'A chronicle of dedication, impact, and continuous growth.',
                    'milestones' => self::milestones($ctx),
                ],
            ],
            [
                'section_type'  => 'gallery',
                'variant'       => 'masonry-grid',
                'display_order' => 6,
                'config'        => ['heading' => 'Gallery', 'eyebrow' => 'Highlights'],
            ],
            [
                'section_type'  => 'office_bearers',
                'variant'       => 'photo-cards',
                'display_order' => 7,
                'config'        => ['heading' => 'Office Bearers', 'eyebrow' => 'Leadership'],
            ],
            [
                'section_type'  => 'member_schools',
                'variant'       => 'modern-grid',
                'display_order' => 8,
                'config'        => ['heading' => 'Member Schools', 'eyebrow' => 'Our Network'],
            ],
            [
                'section_type'  => 'testimonials_sahodaya',
                'variant'       => 'principal-quotes',
                'display_order' => 9,
                'config'        => [
                    'heading' => 'Voices from Member Schools',
                    'quotes'  => [
                        ['name' => 'Principal', 'school' => 'Member CBSE School', 'text' => $ctx['short_name'].' provides our students with platforms to showcase talent beyond the classroom.'],
                        ['name' => 'Teacher Coordinator', 'school' => 'Member School', 'text' => 'Collaborative programmes have enriched professional development for educators across our cluster.'],
                        ['name' => 'School Administrator', 'school' => 'CBSE School, '.$ctx['region'], 'text' => 'Membership opens doors to cluster-level competitions, Kalotsav, and shared academic resources.'],
                    ],
                ],
            ],
            [
                'section_type'  => 'news_circulars',
                'variant'       => 'grid',
                'display_order' => 10,
                'config'        => [
                    'heading'    => 'News & Events',
                    'subheading' => 'Stay updated with the latest happenings, programmes, and circulars from '.$ctx['name'].'.',
                ],
            ],
            [
                'section_type'  => 'contact',
                'variant'       => 'stacked',
                'display_order' => 11,
                'config'        => [
                    'heading' => 'Contact Us',
                    'address' => $ctx['address'],
                    'phone'   => $ctx['phone'],
                    'email'   => $ctx['email'],
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function cmsPages(Tenant $tenant): array
    {
        $ctx = self::context($tenant);

        $pages = [
            'about' => [
                'title'        => 'About Us',
                'subtitle'     => 'About Us',
                'content_html' => '<p><strong>'.$ctx['name'].'</strong> is a CBSE Sahodaya School Complex serving member schools in '.$ctx['region'].'.</p><p>'.$ctx['tagline'].'</p><p>We coordinate academic programmes, Kalotsav, sports meets, teacher development, and membership services for affiliated schools in our cluster.</p>',
            ],
            'contact' => [
                'title'        => 'Contact Us',
                'subtitle'     => 'Contact Us',
                'content_html' => self::contactPageHtml($ctx),
            ],
            'downloads' => [
                'title'        => 'Downloads',
                'subtitle'     => 'Downloads',
                'content_html' => '<p>Access circulars, results, and important documents from '.$ctx['name'].'. Files are managed from the Sahodaya admin panel.</p>',
            ],
            'gallery/function' => [
                'title'        => 'Function Gallery',
                'subtitle'     => 'Function Gallery',
                'content_html' => '<p>Photos from official functions and ceremonies of '.$ctx['short_name'].'.</p>',
            ],
            'gallery/programme' => [
                'title'        => 'Programme Gallery',
                'subtitle'     => 'Programme Gallery',
                'content_html' => '<p>Photos from Kalotsav, sports meets, and other programmes organised by '.$ctx['short_name'].'.</p>',
            ],
            'gallery/sahodya' => [
                'title'        => 'Sahodaya Gallery',
                'subtitle'     => 'Sahodaya Gallery',
                'content_html' => '<p>Gallery highlighting Sahodaya activities and events in '.$ctx['region'].'.</p>',
            ],
        ];

        if ($ctx['is_state']) {
            $pages['executive'] = [
                'title'         => 'Executive Committee',
                'subtitle'      => 'Executive Committee',
                'table_headers' => ['#', 'Member Name', 'Sahodaya Complex'],
                'table_rows'    => CkscContentDefaults::executiveCommitteeRows(),
            ];
            $pages += CkscContentDefaults::stateMoaPages();
        } else {
            $pages['executive'] = self::officeBearersPage($tenant, $ctx);
        }

        return $pages;
    }

    /** Refresh tenant-specific copy on existing sections without changing layout structure. */
    public static function personalizeExistingSections(Tenant $tenant): void
    {
        $fresh = collect(self::homepageSections($tenant))->keyBy(fn ($s) => $s['section_type'].':'.$s['variant']);

        foreach ($tenant->sections()->get() as $section) {
            $key = $section->section_type.':'.$section->variant;
            if ($fresh->has($key)) {
                $section->update(['config' => $fresh[$key]['config']]);
            }
        }
    }

    private static function profile(Tenant $tenant): ?SahodayaProfile
    {
        return SahodayaProfile::where('tenant_id', $tenant->id)->first();
    }

    private static function isStateLevel(Tenant $tenant): bool
    {
        $sub = strtolower($tenant->subdomain ?? '');

        return in_array($sub, ['cksc', 'confederation', 'kerala', 'state'], true)
            || str_contains(strtolower($tenant->name), 'confederation');
    }

    private static function regionLabel(Tenant $tenant): string
    {
        $name = $tenant->name;
        if (preg_match('/(\w+)\s+Sahodaya/i', $name, $m)) {
            return $m[1];
        }

        return $tenant->subdomain ? ucfirst(str_replace('-', ' ', $tenant->subdomain)) : 'Kerala';
    }

    private static function shortName(Tenant $tenant, string $region): string
    {
        if (str_contains(strtolower($tenant->name), 'sahodaya')) {
            return $tenant->name;
        }

        return $region.' Sahodaya';
    }

    private static function tagline(Tenant $tenant, string $region): string
    {
        return 'Uniting CBSE schools in '.$region.' for academic excellence, cultural programmes, and collaborative growth.';
    }

    private static function aboutText(Tenant $tenant, array $ctx): string
    {
        return $ctx['name'].' is a CBSE Sahodaya School Complex in '.$ctx['region'].', fostering collaboration among affiliated schools through academic programmes, sports meets, cultural festivals, and teacher development initiatives.'."\n\n"
            .'Guided by the Sahodaya philosophy of '.$ctx['motto'].', we work together to create meaningful opportunities for students, teachers, and member schools across our cluster.';
    }

    /** @return list<array<string, string>> */
    private static function milestones(array $ctx): array
    {
        return [
            ['year' => '2010', 'title' => 'Cluster Formation', 'description' => 'Member schools in '.$ctx['region'].' come together under the Sahodaya umbrella.'],
            ['year' => '2015', 'title' => 'Kalotsav & Sports', 'description' => 'Regular cluster-level Kalotsav and athletic meets established.'],
            ['year' => '2020', 'title' => 'Digital Coordination', 'description' => 'Online registration and results sharing adopted across member schools.'],
            ['year' => '2024', 'title' => 'Sahodaya Connect', 'description' => $ctx['short_name'].' launches unified membership and event coordination platform.'],
        ];
    }

    /** @param array<string, mixed> $ctx */
    private static function contactPageHtml(array $ctx): string
    {
        $parts = ['<p>Reach the '.$ctx['name'].' office for membership, events, and academic coordination.</p>'];
        if ($ctx['address']) {
            $parts[] = '<p><strong>Address:</strong> '.e($ctx['address']).'</p>';
        }
        if ($ctx['phone']) {
            $parts[] = '<p><strong>Phone:</strong> '.e($ctx['phone']).'</p>';
        }
        if ($ctx['email']) {
            $parts[] = '<p><strong>Email:</strong> '.e($ctx['email']).'</p>';
        }
        $parts[] = '<p><strong>Working Hours:</strong> Mon–Fri 09:30–15:30, Sat 10:00–12:00</p>';

        return implode('', $parts);
    }

    /** @param array<string, mixed> $ctx */
    private static function officeBearersPage(Tenant $tenant, array $ctx): array
    {
        $bearers = OfficeBearers::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['role', 'name', 'school_name']);

        if ($bearers->isEmpty()) {
            return [
                'title'        => 'Office Bearers',
                'subtitle'     => 'Office Bearers',
                'content_html' => '<p>Office bearers for '.$ctx['name'].' are listed on the homepage. Update bearers from Sahodaya Admin → Office Bearers.</p>',
            ];
        }

        return [
            'title'         => 'Office Bearers',
            'subtitle'      => 'Office Bearers',
            'table_headers' => ['Role', 'Name', 'School'],
            'table_rows'    => $bearers->map(fn ($b) => [$b->role, $b->name, $b->school_name ?? '—'])->all(),
        ];
    }
}
