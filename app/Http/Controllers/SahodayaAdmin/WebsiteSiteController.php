<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\WebsiteSite;
use Illuminate\Http\Request;

class WebsiteSiteController extends SahodayaAdminController
{
    public function index()
    {
        WebsiteSite::ensurePrimary($this->sahodaya->id);

        return $this->inertia('Sahodaya/Website/Sites', [
            'sites' => WebsiteSite::where('tenant_id', $this->sahodaya->id)
                ->withCount('sections')
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:80|regex:/^[a-z0-9\-]+$/',
            'seo_json' => 'nullable|array',
            'seo_json.title' => 'nullable|string|max:120',
            'seo_json.description' => 'nullable|string|max:300',
            'seo_json.og_image' => 'nullable|string|max:500',
        ]);

        WebsiteSite::ensurePrimary($this->sahodaya->id);

        WebsiteSite::create([
            'tenant_id' => $this->sahodaya->id,
            'name' => $data['name'],
            'slug' => $data['slug'] ?: WebsiteSite::uniqueSlug($this->sahodaya->id, $data['name']),
            'is_primary' => false,
            'is_active' => true,
            'seo_json' => $data['seo_json'] ?? [],
        ]);

        return back()->with('success', 'Microsite created. Open Website Builder and select it to edit sections.');
    }

    public function update(Request $request, WebsiteSite $site)
    {
        abort_if($site->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'is_active' => 'nullable|boolean',
            'seo_json' => 'nullable|array',
        ]);

        $site->update($data);

        return back()->with('success', 'Site updated.');
    }

    public function destroy(WebsiteSite $site)
    {
        abort_if($site->tenant_id !== $this->sahodaya->id, 403);
        abort_if($site->is_primary, 422, 'Primary site cannot be deleted.');

        $site->sections()->update(['site_id' => null]);
        $site->delete();

        return back()->with('success', 'Microsite removed.');
    }
}
