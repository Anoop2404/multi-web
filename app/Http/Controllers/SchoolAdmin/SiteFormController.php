<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SiteForm;
use App\Models\WebsiteSite;
use Illuminate\Http\Request;

class SiteFormController extends SchoolAdminController
{
    public function index()
    {
        SiteForm::ensureDefaultContact(
            $this->school->id,
            WebsiteSite::ensurePrimary($this->school->id)->id,
        );

        return $this->inertia('School/Website/Forms', [
            'forms' => SiteForm::query()
                ->where('tenant_id', $this->school->id)
                ->withCount('submissions')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creating: true);

        SiteForm::create([
            'tenant_id' => $this->school->id,
            'site_id' => WebsiteSite::ensurePrimary($this->school->id)->id,
            'name' => $data['name'],
            'slug' => SiteForm::uniqueSlug($this->school->id, $data['name']),
            'fields_json' => $data['fields_json'] ?? SiteForm::defaultContactFields(),
            'success_message' => $data['success_message'] ?? 'Thank you — we received your message.',
            'notify_email' => $data['notify_email'] ?? null,
            'honeypot_enabled' => $data['honeypot_enabled'] ?? true,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Website form created.');
    }

    public function update(Request $request, string $tenantId, SiteForm $form)
    {
        abort_if($form->tenant_id !== $this->school->id, 403);

        $form->update($this->validated($request));

        return back()->with('success', 'Website form updated.');
    }

    public function destroy(string $tenantId, SiteForm $form)
    {
        abort_if($form->tenant_id !== $this->school->id, 403);
        abort_if($form->slug === 'contact', 422, 'The default contact form can be hidden, but not removed.');

        $form->submissions()->delete();
        $form->delete();

        return back()->with('success', 'Website form removed.');
    }

    public function submissions(string $tenantId, SiteForm $form)
    {
        abort_if($form->tenant_id !== $this->school->id, 403);

        return $this->inertia('School/Website/FormSubmissions', [
            'form' => $form,
            'submissions' => $form->submissions()->orderByDesc('id')->limit(200)->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'fields_json' => 'nullable|array|min:1|max:20',
            'fields_json.*.key' => ['required_with:fields_json', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'not_in:website_url,_token', 'distinct'],
            'fields_json.*.label' => 'required_with:fields_json|string|max:120',
            'fields_json.*.type' => 'required_with:fields_json|in:text,email,tel,textarea,date,number',
            'fields_json.*.placeholder' => 'nullable|string|max:160',
            'fields_json.*.required' => 'nullable|boolean',
            'success_message' => 'nullable|string|max:500',
            'notify_email' => 'nullable|email|max:255',
            'is_active' => ($creating ? 'nullable' : 'required').'|boolean',
            'honeypot_enabled' => ($creating ? 'nullable' : 'required').'|boolean',
        ]);
    }
}
