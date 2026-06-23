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

    /** @return array<string, mixed> */
    public static function forSahodaya(): array
    {
        return [
            'style'          => 'sahodaya-modern',
            'layout_variant' => 'sahodaya-modern',
            'items'          => [
                ['label' => 'Home', 'url' => '/', 'external' => false, 'children' => []],
                ['label' => 'About', 'url' => '/#about-sahodaya', 'external' => false, 'children' => []],
                ['label' => 'Programmes', 'url' => '/#programmes', 'external' => false, 'children' => []],
                ['label' => 'Office Bearers', 'url' => '/#office-bearers', 'external' => false, 'children' => []],
                ['label' => 'Member Schools', 'url' => '/#member-schools', 'external' => false, 'children' => []],
                [
                    'label' => 'Academic', 'url' => '/#academic-quicklinks', 'external' => false,
                    'children' => [
                        ['label' => 'Membership Renewal', 'url' => '/school-register', 'external' => false],
                    ],
                ],
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
                ['label' => 'About', 'url' => '/#about', 'external' => false, 'children' => []],
                ['label' => 'Academics', 'url' => '/#academic-programmes', 'external' => false, 'children' => []],
                ['label' => 'Admissions', 'url' => '/#admissions', 'external' => false, 'children' => []],
                ['label' => 'Gallery', 'url' => '/#gallery', 'external' => false, 'children' => []],
                ['label' => 'Contact', 'url' => '/#contact', 'external' => false, 'children' => []],
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
