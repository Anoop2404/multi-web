<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SiteSection;
use App\Support\SectionFieldRegistry;
use App\Support\TenantDomainSync;
use Illuminate\Http\JsonResponse;

class SiteBuilderController extends SahodayaAdminController
{
    /** Section types available to Sahodaya admins — curated for their site. */
    private const SAHODAYA_SECTION_TYPES = [
        'hero'                 => ['centered', 'sahodaya-centered', 'split-image', 'gradient-split', 'event-promo', 'with-quicklinks', 'full-bleed'],
        'about_sahodaya'       => ['single-column', 'with-stats', 'motto-hero', 'vision-mission', 'with-timeline'],
        'office_bearers'       => ['photo-cards', 'modern-grid', 'table-list'],
        'member_schools'       => ['modern-grid', 'card-grid', 'table-list', 'map-view'],
        'news_circulars'       => ['modern-feed', 'grid', 'list'],
        'events_programs'      => ['upcoming-cards', 'cards', 'timeline'],
        'kalotsav'             => ['results-tabs', 'scoreboard', 'registration-cta'],
        'sports_meet'          => ['results-highlight', 'schedule-cards'],
        'statistics'           => ['counter-strip', 'horizontal-strip', 'counter-cards'],
        'programmes'           => ['service-grid'],
        'academic_quicklinks'  => ['year-tabs'],
        'downloads_sahodaya'   => ['sahodaya-grid'],
        'circulars'            => ['category-filter', 'accordion'],
        'testimonials_sahodaya'=> ['principal-quotes'],
        'useful_links'         => ['icon-grid'],
        'gallery'              => ['masonry-grid', 'carousel', 'album-based'],
        'contact'              => ['side-by-side', 'stacked', 'with-whatsapp'],
        'newsletter'           => ['subscribe-form'],
        'sahodaya_home'        => ['dashboard'],
    ];

    public function index(): \Inertia\Response
    {
        $sections = SiteSection::where('tenant_id', $this->sahodaya->id)
            ->orderBy('display_order')
            ->get();

        return $this->inertia('Sahodaya/SiteBuilder', [
            'sections'      => $sections,
            'sectionTypes'  => self::SAHODAYA_SECTION_TYPES,
            'fieldDefs'     => SectionFieldRegistry::all(),
        ]);
    }

    public function sectionTypes(): JsonResponse
    {
        return response()->json([
            'types'    => self::SAHODAYA_SECTION_TYPES,
            'fieldDefs'=> SectionFieldRegistry::all(),
        ]);
    }
}
