<?php

namespace App\Support;

use App\Models\Tenant;

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
     * @param  \Illuminate\Support\Collection<int, mixed>  $sections
     * @return array<string, mixed>
     */
    public static function pruneDeadAnchors(array $navConfig, \Illuminate\Support\Collection $sections): array
    {
        $liveAnchors = $sections
            ->pluck('section_type')
            ->filter()
            ->map(fn (string $type) => str_replace('_', '-', $type))
            ->unique();

        $navConfig['items'] = self::filterDeadAnchorItems($navConfig['items'] ?? [], $liveAnchors);

        return $navConfig;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  \Illuminate\Support\Collection<int, string>  $liveAnchors
     * @return array<int, array<string, mixed>>
     */
    private static function filterDeadAnchorItems(array $items, \Illuminate\Support\Collection $liveAnchors): array
    {
        return collect($items)
            ->filter(function (array $item) use ($liveAnchors) {
                if (! preg_match('/^\/#(.+)$/', $item['url'] ?? '', $matches)) {
                    return true;
                }

                return $liveAnchors->contains($matches[1]);
            })
            ->map(function (array $item) use ($liveAnchors) {
                if (! empty($item['children'])) {
                    $item['children'] = self::filterDeadAnchorItems($item['children'], $liveAnchors);
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function forSahodaya(): array
    {
        return [
            'style'          => 'sahodaya-modern',
            'layout_variant' => 'sahodaya-modern',
            'items'          => [
                ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
                ['label' => 'About', 'url' => '/#about-sahodaya', 'external' => false, 'children' => []],
                ['label' => 'Programmes', 'url' => '/#events-programs', 'external' => false, 'children' => []],
                [
                    'label' => 'Events & Results', 'url' => '/fest', 'external' => false,
                    'children' => [
                        ['label' => 'All Events & Schedule', 'url' => '/fest', 'external' => false],
                        ['label' => 'Live Scoreboards', 'url' => '/fest', 'external' => false],
                        ['label' => 'MCQ Talent Search Papers', 'url' => '/mcq/papers', 'external' => false],
                    ],
                ],
                ['label' => 'Office Bearers', 'url' => '/office-bearers', 'external' => false, 'children' => []],
                ['label' => 'Member Schools', 'url' => '/member-schools', 'external' => false, 'children' => []],
                ['label' => 'Gallery', 'url' => '/gallery', 'external' => false, 'children' => []],
                ['label' => 'Circulars', 'url' => '/circulars', 'external' => false, 'children' => []],
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
            'style'          => 'logo-left',
            'layout_variant' => 'logo-left',
            'items'          => [
                ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
                [
                    'label' => 'About Us', 'url' => '/about', 'external' => false,
                    'children' => [
                        ['label' => 'Our Profile', 'url' => '/about', 'external' => false],
                        ['label' => "Principal's Desk", 'url' => '/about#principals-desk', 'external' => false],
                        ['label' => 'Why Choose Us', 'url' => '/about#why-choose', 'external' => false],
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
                ['label' => 'Faculty', 'url' => '/about#faculty', 'external' => false, 'children' => []],
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
}
