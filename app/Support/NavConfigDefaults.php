<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Collection;

class NavConfigDefaults
{
    /** @return array<string, mixed> */
    public static function forTenant(Tenant $tenant): array
    {
        return $tenant->type === 'sahodaya'
            ? self::forSahodaya()
            : self::forSchool($tenant);
    }

    /**
     * Merge stored nav with defaults for public rendering (never overwrites DB).
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function resolve(Tenant $tenant, array $stored): array
    {
        $defaults = self::forTenant($tenant);

        if (empty($stored['items'])) {
            $stored['items'] = $defaults['items'];
        }

        if (empty($stored['layout_variant']) && empty($stored['style'])) {
            $stored['layout_variant'] = $defaults['layout_variant'];
            $stored['style'] = $defaults['style'];
        }

        $stored['portal_cta'] = array_merge(
            $defaults['portal_cta'] ?? [],
            $stored['portal_cta'] ?? []
        );

        if ($tenant->type === 'sahodaya') {
            return PortalNavLinks::mergePortalCta($stored);
        }

        $stored['items'] = self::replaceLegacySchoolLinks($stored['items'] ?? []);

        return SchoolPortalNavLinks::mergePortalCta($stored);
    }

    /**
     * Drop any `/#slug` nav item whose anchor doesn't correspond to a section actually
     * rendered on the page (site-section-frame.blade.php gives every section an
     * id="{section_type with _ replaced by -}"). Non-anchor URLs (real routes, external
     * links) always pass through untouched. Recurses into `children` so a dropdown
     * doesn't keep dead sub-links either.
     *
     * @param  array<string, mixed>  $navConfig
     * @param  Collection<int, mixed>  $sections
     * @return array<string, mixed>
     */
    public static function pruneDeadAnchors(array $navConfig, Collection $sections): array
    {
        $liveAnchors = $sections
            ->pluck('section_type')
            ->filter()
            ->map(fn (string $type) => str_replace('_', '-', $type))
            ->unique();

        $navConfig['items'] = self::filterDeadAnchorItems($navConfig['items'] ?? [], $liveAnchors);

        return $navConfig;
    }

    private const ANCHOR_ALIASES = [
        'about' => ['about', 'about-sahodaya'],
        'about-sahodaya' => ['about', 'about-sahodaya'],
        'programmes' => ['programmes', 'events-programs', 'academic-programmes'],
        'events' => ['events', 'events-programs'],
        'events-programs' => ['events', 'programmes', 'events-programs'],
        'academic' => ['academic', 'academic-programmes', 'events-programs', 'programmes'],
        'news' => ['news', 'news-circulars', 'circulars'],
        'circulars' => ['news', 'news-circulars', 'circulars'],
        'testimonials' => ['testimonials', 'testimonials-sahodaya'],
        'testimonials-sahodaya' => ['testimonials', 'testimonials-sahodaya'],
        'office-bearers' => ['office-bearers', 'management', 'staff'],
        'member-schools' => ['member-schools'],
        'gallery' => ['gallery', 'video-gallery'],
        'contact' => ['contact'],
        'statistics' => ['statistics'],
    ];

    private const ROUTE_FALLBACKS = [
        'about' => '/about',
        'office-bearers' => '/office-bearers',
        'member-schools' => '/member-schools',
        'programmes' => '/fest',
        'events' => '/fest',
        'gallery' => '/gallery',
        'circulars' => '/circulars',
        'downloads' => '/downloads',
        'contact' => '/contact',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, string>  $liveAnchors
     * @return array<int, array<string, mixed>>
     */
    private static function filterDeadAnchorItems(array $items, Collection $liveAnchors): array
    {
        return collect($items)
            ->map(function (array $item) use ($liveAnchors) {
                if (! empty($item['children'])) {
                    $item['children'] = self::filterDeadAnchorItems($item['children'], $liveAnchors);
                }

                if (! preg_match('/^\/#(.+)$/', $item['url'] ?? '', $matches)) {
                    return $item;
                }

                $anchor = $matches[1];

                if ($liveAnchors->contains($anchor)) {
                    return $item;
                }

                foreach (self::ANCHOR_ALIASES[$anchor] ?? [] as $alias) {
                    if ($liveAnchors->contains($alias)) {
                        $item['url'] = '/#'.$alias;
                        return $item;
                    }
                }

                // Keep dropdown parent if it has non-empty children
                if (! empty($item['children'])) {
                    return $item;
                }

                // If anchor is not rendered inline, fall back to dedicated page route if available
                if (isset(self::ROUTE_FALLBACKS[$anchor])) {
                    $item['url'] = self::ROUTE_FALLBACKS[$anchor];
                    return $item;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function forSahodaya(): array
    {
        return [
            'style' => 'sahodaya-modern',
            'layout_variant' => 'sahodaya-modern',
            'items' => [
                ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],

                // ── About ────────────────────────────────────────────
                [
                    'label' => 'About',
                    'url' => '/#about-sahodaya',
                    'external' => false,
                    'children' => [
                        ['label' => 'About Us', 'url' => '/#about-sahodaya', 'external' => false],
                        ['label' => 'Management', 'url' => '/#management', 'external' => false],
                        ['label' => "Principal's Message", 'url' => '/#principal-message', 'external' => false],
                        ['label' => 'Faculty', 'url' => '/#staff', 'external' => false],
                        ['label' => 'Facilities', 'url' => '/#facilities', 'external' => false],
                        ['label' => 'Statistics', 'url' => '/#statistics', 'external' => false],
                    ],
                ],

                // ── Programmes ──────────────────────────────────────
                [
                    'label' => 'Programmes',
                    'url' => '/#academic-programmes',
                    'external' => false,
                    'children' => [
                        ['label' => 'Academic Programmes', 'url' => '/#academic-programmes', 'external' => false],
                        ['label' => 'Board Results', 'url' => '/#board-results', 'external' => false],
                        ['label' => 'Admissions', 'url' => '/#admissions', 'external' => false],
                        ['label' => 'CBSE Disclosure', 'url' => '/#mandatory-disclosure', 'external' => false],
                        ['label' => 'House System', 'url' => '/#house-system', 'external' => false],
                        ['label' => 'Clubs', 'url' => '/#clubs', 'external' => false],
                        ['label' => 'Career Guidance', 'url' => '/#career-guidance', 'external' => false],
                        ['label' => 'ATAL Lab', 'url' => '/#atl', 'external' => false],
                    ],
                ],

                // ── Events & Results ────────────────────────────────
                [
                    'label' => 'Events & Results',
                    'url' => '/fest',
                    'external' => false,
                    'children' => [
                        ['label' => 'All Events & Schedule', 'url' => '/fest', 'external' => false],
                        ['label' => 'Live Scoreboards', 'url' => '/fest', 'external' => false],
                        ['label' => 'MCQ Talent Search', 'url' => '/mcq/papers', 'external' => false],
                        ['label' => 'News', 'url' => '/#news', 'external' => false],
                        ['label' => 'Gallery', 'url' => '/gallery', 'external' => false],
                        ['label' => 'Video Gallery', 'url' => '/#video-gallery', 'external' => false],
                        ['label' => 'Achievements', 'url' => '/#achievements', 'external' => false],
                    ],
                ],

                // ── Community ───────────────────────────────────────
                [
                    'label' => 'Community',
                    'url' => '/member-schools',
                    'external' => false,
                    'children' => [
                        ['label' => 'Office Bearers', 'url' => '/office-bearers', 'external' => false],
                        ['label' => 'Member Schools', 'url' => '/member-schools', 'external' => false],
                        ['label' => 'Circulars', 'url' => '/circulars', 'external' => false],
                        ['label' => 'Testimonials', 'url' => '/#testimonials', 'external' => false],
                        ['label' => 'Awards', 'url' => '/#achievements', 'external' => false],
                        ['label' => 'Alumni', 'url' => '/#alumni', 'external' => false],
                        ['label' => 'Downloads', 'url' => '/#downloads', 'external' => false],
                        ['label' => 'Publications', 'url' => '/#publications', 'external' => false],
                        ['label' => 'Newsletter', 'url' => '/#newsletter', 'external' => false],
                        ['label' => 'FAQ', 'url' => '/#faq', 'external' => false],
                    ],
                ],

                ['label' => 'Membership Renewal', 'url' => '/school-register', 'external' => false, 'children' => []],

                ['label' => 'Contact', 'url' => '/#contact', 'external' => false, 'children' => []],
            ],
            'portal_cta' => PortalNavLinks::portalCtaDefaults(),
        ];
    }

    /** @return array<string, mixed> */
    public static function forSchool(Tenant $school): array
    {
        return [
            'style' => 'logo-left',
            'layout_variant' => 'logo-left',
            'items' => [
                ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
                [
                    'label' => 'About Us', 'url' => '/about', 'external' => false,
                    'children' => [
                        ['label' => 'Our Profile', 'url' => '/about', 'external' => false],
                        ['label' => "Principal's Desk", 'url' => '/about#principal-message', 'external' => false],
                        ['label' => 'Why Choose Us', 'url' => '/about#facilities', 'external' => false],
                    ],
                ],
                [
                    'label' => 'Academics', 'url' => '/academics', 'external' => false,
                    'children' => [
                        ['label' => 'School Overview', 'url' => '/academics', 'external' => false],
                        ['label' => 'CBSE Mandatory Disclosure', 'url' => '/disclosure', 'external' => false],
                        ['label' => 'Results & Achievements', 'url' => '/results', 'external' => false],
                    ],
                ],
                [
                    'label' => 'Admissions', 'url' => '/admissions', 'external' => false,
                    'children' => [
                        ['label' => 'Admission Information', 'url' => '/admissions', 'external' => false],
                        ['label' => 'Admission Enquiry', 'url' => '/admission-enquiry', 'external' => false],
                    ],
                ],
                ['label' => 'Faculty', 'url' => '/faculty', 'external' => false, 'children' => []],
                ['label' => 'Gallery', 'url' => '/gallery', 'external' => false, 'children' => []],
                ['label' => 'Contact Us', 'url' => '/contact', 'external' => false, 'children' => []],
            ],
            'portal_cta' => SchoolPortalNavLinks::portalCtaDefaults(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function layoutOptions(string $tenantType): array
    {
        if ($tenantType === 'sahodaya') {
            return [
                ['value' => 'cksc-pill', 'label' => 'CKSC Pill Menu (recommended)'],
                ['value' => 'sahodaya-modern', 'label' => 'Sahodaya Modern'],
                ['value' => 'logo-left', 'label' => 'Logo Left'],
                ['value' => 'logo-center', 'label' => 'Logo Center'],
                ['value' => 'centered-below', 'label' => 'Centered Below'],
                ['value' => 'dark', 'label' => 'Dark'],
            ];
        }

        return [
            ['value' => 'logo-left', 'label' => 'Logo Left (recommended)'],
            ['value' => 'logo-center', 'label' => 'Logo Center'],
            ['value' => 'centered-below', 'label' => 'Centered Below'],
            ['value' => 'sticky-transparent', 'label' => 'Sticky Transparent'],
            ['value' => 'dark', 'label' => 'Dark'],
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private static function replaceLegacySchoolLinks(array $items): array
    {
        $replacements = [
            '/about#principals-desk' => '/about#principal-message',
            '/about#why-choose' => '/about#facilities',
            '/about#faculty' => '/faculty',
        ];

        return collect($items)->map(function (array $item) use ($replacements) {
            $item['url'] = $replacements[$item['url'] ?? ''] ?? ($item['url'] ?? '');
            if (! empty($item['children'])) {
                $item['children'] = self::replaceLegacySchoolLinks($item['children']);
            }

            return $item;
        })->all();
    }

    /**
     * Build nav items from the current set of active, show_in_menu sections.
     * Each section becomes an anchor link: /#{section_type_with-dashes}.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\SiteSection>  $sections
     * @return list<array{label: string, url: string, external: bool, children: list<array>}>
     */
    public static function itemsFromSections(Collection $sections): array
    {
        $excluded = ['hero', 'membership_cta', 'admission_banner'];

        return $sections
            ->filter(fn ($s) => $s->is_active && $s->show_in_menu && ! in_array($s->section_type, $excluded, true))
            ->sortBy('display_order')
            ->values()
            ->map(function ($s) {
                return [
                    'label' => self::sectionLabel($s->section_type),
                    'url' => '/#'.str_replace('_', '-', $s->section_type),
                    'external' => false,
                    'children' => [],
                ];
            })
            ->all();
    }

    /** @return array<string, string> */
    private static array $sectionLabels = [
        'hero' => 'Home',
        'about' => 'About Us',
        'about_sahodaya' => 'About',
        'principal_message' => "Principal's Message",
        'management' => 'Management',
        'staff' => 'Faculty',
        'facilities' => 'Facilities',
        'statistics' => 'Statistics',
        'academic_programmes' => 'Academics',
        'board_results' => 'Results',
        'admissions' => 'Admissions',
        'mandatory_disclosure' => 'CBSE Disclosure',
        'contact' => 'Contact Us',
        'news' => 'News',
        'events' => 'Events',
        'gallery' => 'Gallery',
        'video_gallery' => 'Video Gallery',
        'achievements' => 'Achievements',
        'downloads' => 'Downloads',
        'alumni' => 'Alumni',
        'job_vacancies' => 'Careers',
        'house_system' => 'House System',
        'clubs' => 'Clubs',
        'career_guidance' => 'Career Guidance',
        'publications' => 'Publications',
        'atl' => 'ATAL Lab',
        'portals' => 'Quick Links',
        'testimonials' => 'Testimonials',
        'newsletter' => 'Newsletter',
        'custom_page' => 'Page',
    ];

    public static function sectionLabel(string $type): string
    {
        return self::$sectionLabels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Append section-derived anchor items to the nav config's items array.
     * Sections that are active and have show_in_menu=true become /#slug links.
     * Does not duplicate URLs already present.
     *
     * @param  array<string, mixed>  $navConfig
     * @param  \Illuminate\Support\Collection<int, \App\Models\SiteSection>  $sections
     * @return array<string, mixed>
     */
    public static function mergeSectionItems(array $navConfig, Collection $sections): array
    {
        $existingUrls = collect($navConfig['items'] ?? [])
            ->flatMap(fn ($item) => self::allUrls($item))
            ->unique()
            ->all();

        $sectionItems = collect(self::itemsFromSections($sections))
            ->reject(fn ($item) => in_array($item['url'], $existingUrls, true))
            ->all();

        $navConfig['items'] = array_merge($navConfig['items'] ?? [], $sectionItems);

        return $navConfig;
    }

    /** @return list<string> */
    private static function allUrls(array $item): array
    {
        $urls = [$item['url'] ?? ''];
        foreach ($item['children'] ?? [] as $child) {
            $urls = array_merge($urls, self::allUrls($child));
        }

        return $urls;
    }
}
