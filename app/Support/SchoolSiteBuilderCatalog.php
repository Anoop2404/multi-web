<?php

namespace App\Support;

class SchoolSiteBuilderCatalog
{
    /** @var array<string, list<string>> */
    public const SECTION_TYPES = [
        'hero'                  => ['centered', 'split-image', 'video-bg', 'minimal', 'with-quicklinks', 'gradient-split', 'full-bleed', 'full-slider'],
        'about'                 => ['text-left', 'text-right', 'two-column', 'with-motto'],
        'principal_message'     => ['card-style', 'full-width', 'with-management'],
        'management'            => ['photo-cards', 'table-list'],
        'statistics'            => ['counter-cards', 'horizontal-strip', 'counter-strip', 'with-achievements'],
        'facilities'            => ['icon-grid', 'image-cards', 'with-virtual-tour'],
        'academic_programmes'   => ['tabs', 'cards', 'with-results'],
        'staff'                 => ['photo-grid', 'table-list', 'department-tabs'],
        'news'                  => ['grid', 'list', 'ticker', 'featured-plus-list'],
        'events'                => ['card-grid', 'timeline', 'list'],
        'gallery'               => ['masonry-grid', 'carousel', 'album-based'],
        'video_gallery'         => ['youtube-grid', 'featured-embed'],
        'board_results'         => ['toppers-cards', 'stats-plus-toppers', 'year-tabs'],
        'achievements'          => ['cards', 'timeline', 'badge-wall'],
        'mandatory_disclosure'  => ['structured', 'accordion'],
        'admissions'            => ['info-block', 'with-form', 'fee-structure'],
        'downloads'             => ['card-grid', 'category-tabs'],
        'alumni'                => ['registration-form', 'featured-grid'],
        'house_system'          => ['color-cards', 'with-points'],
        'clubs'                 => ['icon-grid', 'with-photos'],
        'portals'               => ['quick-links'],
        'testimonials'          => ['carousel', 'card-grid'],
        'career_guidance'       => ['info-block'],
        'publications'          => ['download-cards'],
        'atl'                   => ['feature-block'],
        'custom_page'           => ['freeform'],
        'contact'               => ['side-by-side', 'stacked', 'with-whatsapp'],
        'job_vacancies'         => ['listing'],
        'newsletter'            => ['subscribe-form'],
    ];

    public static function allows(string $sectionType, ?string $variant = null): bool
    {
        if (! isset(self::SECTION_TYPES[$sectionType])) {
            return false;
        }

        if ($variant === null) {
            return true;
        }

        return in_array($variant, self::SECTION_TYPES[$sectionType], true);
    }
}
