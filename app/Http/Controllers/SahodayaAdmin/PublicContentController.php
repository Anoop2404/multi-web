<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FeatureFlags;
use App\Support\SahodayaHomepageContent;
use App\Support\TenantPublicSite;
use Illuminate\Http\Request;

class PublicContentController extends SahodayaAdminController
{
    public function index()
    {
        return $this->inertia('Sahodaya/PublicContent/Index', [
            'content'              => SahodayaHomepageContent::get($this->sahodaya),
            'publicWebsiteEnabled' => TenantPublicSite::isEnabled($this->sahodaya),
        ]);
    }

    public function update(Request $request)
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
        ]);

        if ($request->has('public_website_enabled')) {
            TenantPublicSite::setEnabled($this->sahodaya, $request->boolean('public_website_enabled'));
        }

        SahodayaHomepageContent::update($this->sahodaya, $data);

        $label = FeatureFlags::websiteEnabled() ? 'Website content' : 'Portal content';

        return back()->with('success', "{$label} saved.");
    }
}
