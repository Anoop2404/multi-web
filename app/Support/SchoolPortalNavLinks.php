<?php

namespace App\Support;

class SchoolPortalNavLinks
{
    public const ADMISSIONS_URL = '/#admissions';

    public const LOGIN_URL = '/login';

    /** @return array<string, mixed> */
    public static function portalCtaDefaults(): array
    {
        return [
            'show_in_navbar'    => true,
            'show_in_menu'      => true,
            'register_label'    => 'Admissions',
            'register_url'      => self::ADMISSIONS_URL,
            'login_label'       => 'Admin Login',
            'login_url'         => self::LOGIN_URL,
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
            $navConfig['items'] = self::ensureNavItems($navConfig['items'] ?? []);
        }

        return $navConfig;
    }

    /** @param  list<array<string, mixed>>  $items */
    public static function ensureNavItems(array $items): array
    {
        foreach ([
            ['label' => 'Admissions', 'url' => self::ADMISSIONS_URL, 'external' => false, 'children' => []],
            ['label' => 'Admin Login', 'url' => self::LOGIN_URL, 'external' => false, 'children' => []],
        ] as $link) {
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
