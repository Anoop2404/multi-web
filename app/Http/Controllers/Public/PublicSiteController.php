<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\WebsiteSite;
use Illuminate\Http\Request;
use App\Services\Website\SahodayaTemplateApplier;
use App\Support\SahodayaWebsiteTemplateCatalog;
use App\Services\Website\SahodayaHomepageModeResolver;

class PublicSiteController extends Controller
{
    use RendersPublicPages;

    public function home(Request $request)
    {
        $tenant = $this->resolveTenant();
        $site = WebsiteSite::ensurePrimary($tenant->id);

        return $this->renderPublic('public.home', $tenant, [
            // Mandatory disclosure is dense compliance content, not a homepage
            // teaser — it lives only on its own /disclosure page (see page()
            // below), never inline on the homepage.
            'sections' => $site->sectionQuery()->forPublic()
                ->where('section_type', '!=', 'mandatory_disclosure')
                ->orderBy('display_order')->get(),
            'site' => $site,
            'experience' => $this->experienceData($site),
        ]);
    }

    public function preview(Request $request)
    {
        abort_unless(auth()->check(), 403);

        $tenant = $this->resolveTenant();
        abort_unless(auth()->user()?->tenant_id === $tenant->id || auth()->user()?->can('website.manage'), 403);
        $site = WebsiteSite::resolveForTenant(
            $tenant->id,
            $request->filled('site_id') ? $request->integer('site_id') : null,
        );

        $sections = ! empty($site->draft_template_json)
            ? app(SahodayaTemplateApplier::class)->previewSections($site)
            : $site->sectionQuery()->active()->orderBy('display_order')->get();

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $sections,
            'previewMode' => true,
            'site' => $site,
            'microsite' => $site->is_primary ? null : $site,
            'pageSeo' => $site->seo_json ?? [],
            'experience' => $this->experienceData($site, true),
        ]);
    }

    public function page(Request $request, string $page)
    {
        $tenant = $this->resolveTenant();
        $site = WebsiteSite::ensurePrimary($tenant->id);
        $allSections = $site->sectionQuery()->forPublic()->orderBy('display_order')->get();

        $pageLower = strtolower(trim($page));
        switch ($pageLower) {
            case 'about':
            case 'about-us':
                $pageConfig = [
                    'title' => $tenant->type === 'school' ? 'About Our School' : 'About Sahodaya',
                    'eyebrow' => $tenant->type === 'school' ? 'Vision & Values' : 'Network Vision & Leadership',
                    'subheading' => 'Fostering academic excellence, character building, and holistic education.',
                    'section_types' => ['about', 'about_sahodaya', 'facilities', 'statistics'],
                ];
                break;
            case 'academics':
            case 'academic':
            case 'courses':
                $pageConfig = [
                    'title' => 'Academic Programmes',
                    'eyebrow' => 'CBSE Curriculum',
                    'subheading' => 'Comprehensive academic curriculum, innovative teaching methods, and student development.',
                    'section_types' => ['academic_programmes', 'board_results'],
                ];
                break;
            case 'admissions':
            case 'admission':
                $pageConfig = [
                    'title' => 'Admissions',
                    'eyebrow' => 'Join Our School',
                    'subheading' => 'Admission details, eligibility criteria, and application enquiry desk.',
                    'section_types' => ['admissions'],
                ];
                break;
            case 'disclosure':
            case 'mandatory-disclosure':
            case 'cbse-disclosure':
                $pageConfig = [
                    'title' => 'CBSE Mandatory Public Disclosure',
                    'eyebrow' => 'CBSE Affiliation Bye-Laws',
                    'subheading' => 'Mandatory public disclosures, affiliation documents, infrastructure, and governance.',
                    'section_types' => ['mandatory_disclosure'],
                ];
                break;
            case 'contact':
            case 'contact-us':
                $pageConfig = [
                    'title' => 'Contact Us',
                    'eyebrow' => 'Get In Touch',
                    'subheading' => 'Contact our administrative desk or send us an enquiry.',
                    'section_types' => ['contact'],
                ];
                break;
            default:
                $pageConfig = [
                    'title' => ucfirst(str_replace('-', ' ', $page)),
                    'eyebrow' => $tenant->name ?? 'Portal',
                    'subheading' => 'Official page for ' . ($tenant->name ?? 'School') . '.',
                    'section_types' => [str_replace('-', '_', $pageLower)],
                ];
                break;
        }

        $filteredSections = $allSections->filter(function ($section) use ($pageConfig) {
            return in_array($section->section_type, $pageConfig['section_types'], true);
        });

        if ($filteredSections->isEmpty()) {
            $filteredSections = $allSections->reject(fn ($s) => $s->section_type === 'hero');
        }

        return $this->renderPublic('public.microsite.page', $tenant, [
            'sections' => $filteredSections,
            'allSections' => $allSections,
            'microsite' => null,
            'site' => $site,
            'pageConfig' => $pageConfig,
            'activePage' => $page,
            'pageSeo' => array_merge($site->seo_json ?? [], ['title' => $pageConfig['title'] . ' | ' . ($tenant->name ?? 'School')]),
            'experience' => $this->experienceData($site),
        ]);
    }

    public function microsite(Request $request, string $slug)
    {
        $tenant = $this->resolveTenant();
        $site = WebsiteSite::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->renderPublic('public.home', $tenant, [
            'sections' => $site->sectionQuery()->forPublic()->orderBy('display_order')->get(),
            'microsite' => $site,
            'pageSeo' => $site->seo_json ?? [],
            'site' => $site,
            'experience' => $this->experienceData($site),
        ]);
    }

    public function micrositePage(Request $request, string $slug, string $page)
    {
        $tenant = $this->resolveTenant();
        $site = WebsiteSite::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $allSections = $site->sectionQuery()->forPublic()->orderBy('display_order')->get();

        $pageLower = strtolower($page);
        switch ($pageLower) {
            case 'about':
                $pageConfig = [
                    'title' => 'About Sahodaya',
                    'eyebrow' => 'Network Vision & Leadership',
                    'subheading' => 'Fostering academic excellence, teacher empowerment, and educational collaboration across CBSE institutions.',
                    'section_types' => ['about_sahodaya', 'statistics'],
                ];
                break;
            case 'member-schools':
            case 'schools':
            case 'directory':
                $pageConfig = [
                    'title' => 'Member Schools Directory',
                    'eyebrow' => 'Affiliated Network',
                    'subheading' => 'Explore 80+ CBSE affiliated member schools across districts in ' . ($tenant->name ?? 'the region') . '.',
                    'section_types' => ['member_schools'],
                ];
                break;
            case 'office-bearers':
            case 'leadership':
            case 'committee':
                $pageConfig = [
                    'title' => 'Office Bearers & Leadership',
                    'eyebrow' => 'Executive Committee',
                    'subheading' => 'Dedicated educational leaders guiding the vision, academic programmes, and governance of Sahodaya.',
                    'section_types' => ['office_bearers'],
                ];
                break;
            case 'gallery':
            case 'photos':
            case 'media':
                $pageConfig = [
                    'title' => 'Event & Media Gallery',
                    'eyebrow' => 'Sahodaya Showcase',
                    'subheading' => 'Highlights from Kalotsav cultural fests, sports championships, principal summits, and teacher workshops.',
                    'section_types' => ['gallery'],
                ];
                break;
            case 'announcements':
            case 'news':
            case 'circulars':
                $pageConfig = [
                    'title' => 'Official Circulars & Updates',
                    'eyebrow' => 'Mandates & Notices',
                    'subheading' => 'Latest CBSE guidelines, Sahodaya circulars, academic notices, and downloadable instructions.',
                    'section_types' => ['news_circulars', 'downloads_sahodaya'],
                ];
                break;
            case 'events':
            case 'programmes':
            case 'calendar':
                $pageConfig = [
                    'title' => 'Programmes & Event Calendar',
                    'eyebrow' => 'What Is Next',
                    'subheading' => 'Upcoming regional competitions, athletics meets, principal conclaves, and academic workshops.',
                    'section_types' => ['events_programs'],
                ];
                break;
            case 'downloads':
            case 'resources':
                $pageConfig = [
                    'title' => 'Downloads & Resource Center',
                    'eyebrow' => 'Official Documents',
                    'subheading' => 'Downloadable affiliation forms, event rulebooks, syllabus guidelines, and circular archives.',
                    'section_types' => ['downloads_sahodaya', 'news_circulars'],
                ];
                break;
            case 'contact':
            case 'secretariat':
                $pageConfig = [
                    'title' => 'Contact Secretariat',
                    'eyebrow' => 'Connect With Us',
                    'subheading' => 'Get in touch with our secretariat desk for membership enquiries, event details, and circular clarifications.',
                    'section_types' => ['contact'],
                ];
                break;
            default:
                $pageConfig = [
                    'title' => ucfirst(str_replace('-', ' ', $page)),
                    'eyebrow' => 'Sahodaya Portal',
                    'subheading' => 'Official page for ' . ($tenant->name ?? 'Sahodaya') . '.',
                    'section_types' => [],
                ];
                break;
        }

        $filteredSections = $allSections->filter(function ($section) use ($pageConfig) {
            return in_array($section->section_type, $pageConfig['section_types'], true);
        });

        // Fallback: if no matching section is assigned to site, show all sections except hero
        if ($filteredSections->isEmpty() && !empty($pageConfig['section_types'])) {
            $filteredSections = $allSections->reject(fn ($s) => $s->section_type === 'hero');
        }

        return $this->renderPublic('public.microsite.page', $tenant, [
            'sections' => $filteredSections,
            'allSections' => $allSections,
            'microsite' => $site,
            'site' => $site,
            'pageConfig' => $pageConfig,
            'activePage' => $page,
            'pageSeo' => array_merge($site->seo_json ?? [], ['title' => $pageConfig['title'] . ' | ' . ($tenant->name ?? 'Sahodaya')]),
            'experience' => $this->experienceData($site),
        ]);
    }

    /** @return array<string, mixed> */
    private function experienceData(WebsiteSite $site, bool $preview = false): array
    {
        $draft = $preview ? ($site->draft_template_json ?? []) : [];
        $key = $draft['template_key'] ?? $site->template_key;

        return [
            'key' => $key,
            'version' => $draft['template_version'] ?? $site->template_version,
            'experience_version' => $key ? 'v2' : ($site->experience_version ?? 'v1'),
            'homepage_mode' => app(SahodayaHomepageModeResolver::class)->resolve($site),
            'design' => $draft['design'] ?? $site->design_json ?? [],
            'widget_policy' => $draft['widgets'] ?? SahodayaWebsiteTemplateCatalog::widgetPolicy($key),
        ];
    }
}
