<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SiteForm;
use App\Models\SiteFormSubmission;
use App\Models\WebsiteSite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteFormController extends SahodayaAdminController
{
    public function index()
    {
        $forms = SiteForm::where('tenant_id', $this->sahodaya->id)
            ->withCount('submissions')
            ->orderBy('name')
            ->get();

        return $this->inertia('Sahodaya/Website/Forms', [
            'forms' => $forms,
            'sites' => WebsiteSite::where('tenant_id', $this->sahodaya->id)->orderByDesc('is_primary')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'site_id' => 'nullable|integer',
            'fields_json' => 'nullable|array',
            'success_message' => 'nullable|string|max:500',
            'notify_email' => 'nullable|email|max:255',
            'honeypot_enabled' => 'nullable|boolean',
        ]);

        $fields = $data['fields_json'] ?? [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ];

        SiteForm::create([
            'tenant_id' => $this->sahodaya->id,
            'site_id' => $data['site_id'] ?? WebsiteSite::ensurePrimary($this->sahodaya->id)->id,
            'name' => $data['name'],
            'slug' => SiteForm::uniqueSlug($this->sahodaya->id, $data['name']),
            'fields_json' => $fields,
            'success_message' => $data['success_message'] ?? 'Thank you — we received your message.',
            'notify_email' => $data['notify_email'] ?? null,
            'honeypot_enabled' => $data['honeypot_enabled'] ?? true,
            'is_active' => true,
        ]);

        return back()->with('success', 'Form created.');
    }

    public function update(Request $request, SiteForm $form)
    {
        abort_if($form->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'fields_json' => 'nullable|array',
            'success_message' => 'nullable|string|max:500',
            'notify_email' => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
            'honeypot_enabled' => 'nullable|boolean',
        ]);

        $form->update($data);

        return back()->with('success', 'Form updated.');
    }

    public function destroy(SiteForm $form)
    {
        abort_if($form->tenant_id !== $this->sahodaya->id, 403);
        $form->submissions()->delete();
        $form->delete();

        return back()->with('success', 'Form removed.');
    }

    public function submissions(SiteForm $form)
    {
        abort_if($form->tenant_id !== $this->sahodaya->id, 403);

        return $this->inertia('Sahodaya/Website/FormSubmissions', [
            'form' => $form,
            'submissions' => $form->submissions()->orderByDesc('id')->limit(200)->get(),
        ]);
    }
}
