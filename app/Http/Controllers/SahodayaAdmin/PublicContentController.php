<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Services\Website\SahodayaContentReadiness;
use App\Services\Website\SahodayaTemplateApplier;
use App\Support\FeatureFlags;
use App\Support\SahodayaHomepageContent;
use App\Support\SahodayaTenantBranding;
use App\Support\SahodayaWebsiteTemplateCatalog;
use App\Support\TenantPublicSite;
use Illuminate\Http\Request;

class PublicContentController extends SahodayaAdminController
{
    public function index()
    {
        $primarySite = \App\Models\WebsiteSite::ensurePrimary($this->sahodaya->id);

        return $this->inertia('Sahodaya/PublicContent/Index', [
            'content'              => SahodayaHomepageContent::get($this->sahodaya),
            'publicWebsiteEnabled' => TenantPublicSite::isEnabled($this->sahodaya),
            'experienceVersion'    => $primarySite->experience_version ?? 'v1',
            'templateKey'          => $primarySite->template_key,
        ]);
    }

    public function update(Request $request, SahodayaTemplateApplier $applier, SahodayaContentReadiness $readiness)
    {
        $data = $request->validate([
            'heading'            => 'nullable|string|max:255',
            'tagline'            => 'nullable|string|max:500',
            'eyebrow'            => 'nullable|string|max:100',
            'motto'              => 'nullable|string|max:500',
            'about_heading'      => 'nullable|string|max:255',
            'about_text'         => 'nullable|string|max:5000',
            'phone'              => 'nullable|string|max:30',
            'email'              => 'nullable|email|max:255',
            'address'            => 'nullable|string|max:1000',
            'contact_heading'    => 'nullable|string|max:255',
            'contact_text'       => 'nullable|string|max:2000',
            'programmes_heading' => 'nullable|string|max:255',
            'academic_heading'   => 'nullable|string|max:255',
            'links_heading'      => 'nullable|string|max:255',
            'announcements'      => 'nullable|array',
            'announcements.*.title' => 'required_with:announcements|string|max:255',
            'announcements.*.url'   => 'nullable|string|max:500',
            'announcements.*.date'  => 'nullable|string|max:50',
            'announcements.*.badge' => 'nullable|string|max:50',
            'programmes'         => 'nullable|array',
            'programmes.*.label'       => 'required_with:programmes|string|max:100',
            'programmes.*.description' => 'nullable|string|max:255',
            'programmes.*.url'         => 'nullable|string|max:500',
            'programmes.*.icon'        => 'nullable|string|max:10',
            'years'              => 'nullable|array',
            'years.*.year'       => 'required_with:years|string|max:20',
            'years.*.links'      => 'nullable|array',
            'years.*.links.*.label' => 'required_with:years.*.links|string|max:100',
            'years.*.links.*.url'   => 'nullable|string|max:500',
            'years.*.links.*.icon'  => 'nullable|string|max:10',
            'links'              => 'nullable|array',
            'links.*.label'      => 'required_with:links|string|max:100',
            'links.*.url'        => 'nullable|string|max:500',
            'links.*.icon'       => 'nullable|string|max:10',
            'public_website_enabled' => 'nullable|boolean',
            'experience_version'     => 'nullable|string|in:v1,v2',
        ]);

        if ($request->has('public_website_enabled')) {
            TenantPublicSite::setEnabled($this->sahodaya, $request->boolean('public_website_enabled'));
        }

        // Save content (heading, contact info, etc.) before acting on experience_version below —
        // switching to V2 validates readiness (logo/contact present) against this same tenant, so
        // contact details entered in the same submit must already be persisted when that check runs.
        SahodayaHomepageContent::update($this->sahodaya, $data);

        if ($request->filled('experience_version')) {
            $site = \App\Models\WebsiteSite::ensurePrimary($this->sahodaya->id);
            $newVer = $request->input('experience_version');

            if ($newVer === 'v2' && ! $site->sections()->exists()) {
                // No section actually scoped to this site yet (either never enabled before, or an
                // earlier version of this toggle only flipped the flag without applying content) —
                // apply a real template, same mechanism the Site Builder's "apply experience" uses,
                // instead of leaving visitors on the legacy fallback page.
                $templateKey = $site->template_key && array_key_exists($site->template_key, SahodayaWebsiteTemplateCatalog::all())
                    ? $site->template_key
                    : 'events-results-live';
                $template = SahodayaWebsiteTemplateCatalog::get($templateKey);
                $context = SahodayaTenantBranding::context($this->sahodaya);
                $applier->applyDraft($this->sahodaya, $site, $templateKey, $template, $context);

                $report = $readiness->inspect($this->sahodaya, $site->fresh());
                if (! $report['ready']) {
                    $applier->cancelDraft($site);

                    return back()->withErrors([
                        'experience_version' => 'Could not switch to the Modern (V2) website yet: '.implode(' ', $report['errors']),
                    ]);
                }

                $applier->publishDraft($site);
            } else {
                $site->update(['experience_version' => $newVer]);
            }

            $this->sahodaya->invalidateCache();
        }

        $label = FeatureFlags::websiteEnabled() ? 'Website content' : 'Portal content';

        return back()->with('success', "{$label} saved.");
    }
}
