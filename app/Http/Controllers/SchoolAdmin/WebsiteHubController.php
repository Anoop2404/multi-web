<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\WebsiteSite;
use App\Services\Licensing\FeatureGate;
use App\Support\TenantPublicSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;

class WebsiteHubController extends SchoolAdminController
{
    public function index(): Response|RedirectResponse
    {
        if (! app(FeatureGate::class)->allows($this->school, 'module.website')) {
            return Redirect::route('school-admin.dashboard', ['tenantId' => $this->school->id])
                ->with(
                    'error',
                    'Your subscription does not include the public website module. Contact your Sahodaya administrator to enable it.',
                );
        }

        $base = "/school-admin/{$this->school->id}";
        $site = WebsiteSite::ensurePrimary($this->school->id);

        $sections = $site->sectionQuery()->get();
        $activeCount = $sections->where('is_active', true)->count();
        $draftCount = $sections->filter(fn ($s) => $s->hasUnpublishedChanges())->count();

        $readiness = $this->readiness($site, $sections);
        $percentage = $readiness['percentage'];

        return $this->inertia('School/Website/Hub', [
            'links' => [
                'site_builder' => "{$base}/site-builder",
                'news' => "{$base}/news",
                'events' => "{$base}/events",
                'gallery' => "{$base}/gallery",
                'staff' => "{$base}/staff",
                'achievements' => "{$base}/achievements",
                'downloads' => "{$base}/downloads",
                'job_vacancies' => "{$base}/job-vacancies",
                'alumni' => "{$base}/alumni",
                'testimonials' => "{$base}/testimonials",
                'forms' => "{$base}/website/forms",
                'contact' => "{$base}/contact",
                'enquiries' => "{$base}/enquiries",
            ],
            'publicWebsiteEnabled' => TenantPublicSite::isEnabled($this->school),
            'readiness' => $readiness,
            'stats' => [
                'news_count' => $this->school->news()->count(),
                'upcoming_events_count' => $this->school->events()->where('start_date', '>=', now()->toDateString())->count(),
                'sections_count' => $sections->count(),
                'active_sections' => $activeCount,
                'draft_sections' => $draftCount,
                'domain_verified' => !empty($this->school->getSetting('public_site_domain')),
            ],
        ]);
    }

    /** @return array{percentage: int, missing: string[]} */
    private function readiness(WebsiteSite $site, $sections): array
    {
        $missing = [];
        $total = 8;
        $passed = 0;

        if (!empty($this->school->getSetting('public_site_domain'))) {
            $passed++;
        } else {
            $missing[] = 'Connect a domain name';
        }

        if ($sections->count() > 0) {
            $passed++;
        } else {
            $missing[] = 'Add at least one website section';
        }

        $hero = $sections->firstWhere('section_type', 'hero');
        if ($hero && $hero->is_active) {
            $passed++;
        } else {
            $missing[] = 'Add and activate a Hero section';
        }

        $contact = $sections->firstWhere('section_type', 'contact');
        if ($contact && $contact->is_active) {
            $passed++;
        } else {
            $missing[] = 'Add contact information';
        }

        $about = $sections->firstWhere('section_type', 'about');
        if ($about && $about->is_active) {
            $passed++;
        } else {
            $missing[] = 'Add an About section';
        }

        if (!empty($site->design_json)) {
            $passed++;
        } else {
            $missing[] = 'Choose a design theme';
        }

        $menuConfigured = !empty($this->school->getSetting('nav_config')['items']);
        if ($menuConfigured) {
            $passed++;
        } else {
            $missing[] = 'Configure the navigation menu';
        }

        if ($sections->every(fn ($s) => !$s->hasUnpublishedChanges())) {
            $passed++;
        } else {
            $missing[] = 'Publish all draft changes';
        }

        return [
            'percentage' => (int) round(($passed / $total) * 100),
            'missing' => $missing,
            'total' => $total,
            'passed' => $passed,
        ];
    }
}
