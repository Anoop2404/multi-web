<?php

namespace App\Support;

class SectionVariantResolver
{
    private const ALIASES = [
        'hero' => [
            'split' => 'split-image',
            'full-image' => 'split-image',
        ],
        'staff' => [
            'card-grid' => 'photo-grid',
            'table' => 'table-list',
        ],
        'news' => [
            'card-grid' => 'grid',
        ],
        'achievements' => [
            'card-grid' => 'cards',
        ],
        'contact' => [
            'split-map' => 'side-by-side',
        ],
        'member_schools' => [
            'logo-grid' => 'card-grid',
            'list-table' => 'table-list',
        ],
        'admissions' => [
            'info-only' => 'info-block',
        ],
        'downloads' => [
            'sahodaya-grid' => 'sahodaya-grid',
        ],
        'downloads_sahodaya' => [
            'grid' => 'sahodaya-grid',
        ],
        'testimonials' => [
            'principal-quotes' => 'principal-quotes',
        ],
        'footer' => [
            'dark' => 'three-column',
            'light' => 'minimal',
        ],
        'nav' => [
            'modern' => 'sahodaya-modern',
        ],
    ];

    public static function resolve(string $type, string $variant): string
    {
        return self::ALIASES[$type][$variant] ?? $variant;
    }

    /** @return array{0: string, 1: string} */
    public static function path(string $type, string $variant): array
    {
        $variant = self::resolve($type, $variant);

        if ($type === 'downloads' && $variant === 'sahodaya-grid') {
            return ['downloads_sahodaya', 'sahodaya-grid'];
        }

        return [$type, $variant];
    }

    public static function resolveFooterVariant(array $footerConfig): string
    {
        $variant = $footerConfig['layout_variant']
            ?? $footerConfig['style']
            ?? 'three-column';

        return self::ALIASES['footer'][$variant] ?? $variant;
    }

    public static function resolveNavVariant(array $navConfig): string
    {
        $variant = $navConfig['layout_variant']
            ?? $navConfig['style']
            ?? 'logo-left';

        return self::ALIASES['nav'][$variant] ?? $variant;
    }
}
