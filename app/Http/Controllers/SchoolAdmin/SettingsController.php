<?php

namespace App\Http\Controllers\SchoolAdmin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends SchoolAdminController
{
    public function index()
    {
        $settings = $this->school->settings()->get()->pluck('value', 'key')->toArray();

        return $this->inertia('School/Settings/Index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'phone'           => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'address'         => 'nullable|string|max:500',
            'address_city'    => 'nullable|string|max:100',
            'facebook'        => 'nullable|url|max:255',
            'youtube'         => 'nullable|url|max:255',
            'instagram'       => 'nullable|url|max:255',
            'logo'            => 'nullable|image|max:2048',
            'seo_title'       => 'nullable|string|max:70',
            'seo_description' => 'nullable|string|max:160',
            'seo_keywords'    => 'nullable|string|max:500',
            'seo_tagline'     => 'nullable|string|max:200',
            'locale'          => 'nullable|string|in:en,ml',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $path = \App\Support\TenantStorage::storeLogo($request->file('logo'), $this->school->id);
            $this->school->setSetting('logo', $path);
        }

        // Save contact info
        $contact = array_filter([
            'phone'   => $data['phone'] ?? null,
            'email'   => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
        if ($contact) {
            $this->school->setSetting('contact', $contact);
        }

        if (!empty($data['address_city'])) {
            $this->school->setSetting('address_city', $data['address_city']);
        }

        // Social links merged into widgets.social_links
        $socials = array_filter([
            'facebook'  => $data['facebook']  ?? null,
            'youtube'   => $data['youtube']   ?? null,
            'instagram' => $data['instagram'] ?? null,
        ]);

        if ($socials) {
            $widgets = $this->school->getWidgets();
            $widgets['social_links'] = array_merge($widgets['social_links'] ?? [], $socials);
            $this->school->setSetting('widgets', $widgets);
        }

        // SEO settings
        $seo = array_filter([
            'title'       => $data['seo_title']       ?? null,
            'description' => $data['seo_description'] ?? null,
            'keywords'    => $data['seo_keywords']    ?? null,
            'tagline'     => $data['seo_tagline']     ?? null,
        ]);
        if ($seo) {
            $existing = $this->school->settings()->where('key', 'seo')->first()?->value ?? [];
            $this->school->setSetting('seo', array_merge($existing, $seo));
        }

        if (! empty($data['locale'])) {
            $this->school->setSetting('locale', $data['locale']);
        }

        Cache::forget("site:{$this->school->id}:home");
        Cache::forget("site:{$this->school->id}:sitemap");

        return back()->with('success', 'Settings saved.');
    }
}
