<?php

namespace App\Support;

class SchoolPortalNavLinks
{
    public const ADMISSIONS_URL = '/#admissions';

    public const LOGIN_URL = '/login';

    public const PORTAL_LOGIN_URL = '/portal/login';

    /** @return array<string, mixed> */
    public static function portalCtaDefaults(): array
    {
        return [
            // Public visitors (parents, students) have no use for an admin login CTA —
            // real school sites keep staff/admin access out of the main nav. The
            // student/parent/teacher self-service portal is genuinely useful to
            // visitors though, so that's what the default CTA button points to.
            'show_in_navbar'    => false,
            'show_in_menu'      => false,
            'register_label'    => 'Admissions',
            'register_url'      => self::ADMISSIONS_URL,
            'login_label'       => 'Student / Parent Portal',
            'login_url'         => self::PORTAL_LOGIN_URL,
        ];
    }

    /** @param  array<string, mixed>  $navConfig */
    public static function mergePortalCta(array $navConfig): array
    {
        $navConfig['portal_cta'] = array_merge(
            self::portalCtaDefaults(),
            $navConfig['portal_cta'] ?? []
        );

        if ($navConfig['portal_cta']['show_in_menu'] ?? false) {
            $navConfig['items'] = self::ensureNavItems(
                $navConfig['items'] ?? [],
                includeLogin: ! ($navConfig['portal_cta']['show_in_navbar'] ?? false)
            );
        }

        return $navConfig;
    }

    /** @param  list<array<string, mixed>>  $items */
    public static function ensureNavItems(array $items, bool $includeLogin = true): array
    {
        $links = [
            ['label' => 'Admissions', 'url' => self::ADMISSIONS_URL, 'external' => false, 'children' => []],
        ];

        // The prominent CTA button (desktop + mobile) already surfaces login when
        // show_in_navbar is on, so a plain menu item alongside it would be a duplicate.
        if ($includeLogin) {
            $links[] = ['label' => 'Admin Login', 'url' => self::LOGIN_URL, 'external' => false, 'children' => []];
        }

        foreach ($links as $link) {
            if (! self::hasNavItem($items, $link['url'])) {
                $items[] = $link;
            }
        }

        return $items;
    }

    /** @param  array<string, mixed>  $footerConfig */
    public static function ensureFooterLinks(array $footerConfig): array
    {
        $links = $footerConfig['quick_links'] ?? [];

        foreach ([
            ['label' => 'Admissions', 'url' => self::ADMISSIONS_URL],
            ['label' => 'Admin Login', 'url' => self::LOGIN_URL],
        ] as $link) {
            $exists = collect($links)->contains(fn ($l) => ($l['url'] ?? '') === $link['url']);
            if (! $exists) {
                $links[] = $link;
            }
        }

        $footerConfig['quick_links'] = $links;

        return $footerConfig;
    }

    /** @param  list<array<string, mixed>>  $items */
    private static function hasNavItem(array $items, string $url): bool
    {
        foreach ($items as $item) {
            if (($item['url'] ?? '') === $url) {
                return true;
            }
            if (! empty($item['children']) && self::hasNavItem($item['children'], $url)) {
                return true;
            }
        }

        return false;
    }
}
