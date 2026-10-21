<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqCertificateTemplate;
use App\Models\McqHallTicketTemplate;
use Illuminate\Http\Request;

class McqTemplateController extends SahodayaAdminController
{
    public function hallTickets()
    {
        $templates = McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        if ($templates->isEmpty()) {
            $default = McqHallTicketTemplate::create([
                'tenant_id'   => $this->sahodaya->id,
                'title'       => 'Official CBSE / Sahodaya MCQ Hall Ticket',
                'design_json' => \App\Support\Mcq\McqHallTicketDesign::defaults(),
                'is_default'  => true,
                'is_active'   => true,
            ]);
            $templates = collect([$default]);
        }

        $defaultDesign = \App\Support\Mcq\McqHallTicketDesign::defaults();
        $samplePreview = \App\Support\Mcq\McqHallTicketDesign::previewSampleData($defaultDesign);

        return $this->inertia('Sahodaya/Mcq/Templates/HallTickets', [
            'templates'     => $templates->map(function ($template) {
                $data = $template->toArray();
                $data['design_json']['background_url'] = \App\Support\TenantStorage::logoUrl(
                    $this->sahodaya, $template->design_json['background_path'] ?? null,
                );
                return $data;
            }),
            'defaultDesign' => $defaultDesign,
            'samplePreview' => $samplePreview,
            'logoUrl'       => \App\Support\TenantStorage::logoUrl($this->sahodaya, null),
        ]);
    }

    public function storeHallTicket(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:120',
            'is_default'   => 'nullable|boolean',
            'design_json'  => 'nullable|array',
            'background' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if (($data['design_json']['layout'] ?? '') === 'custom_background') {
            $request->validate(['background' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048']);
        }

        if ($request->boolean('is_default')) {
            McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)->update(['is_default' => false]);
        }

        $normalizedDesign = \App\Support\Mcq\McqHallTicketDesign::normalize($data['design_json'] ?? null);
        $normalizedDesign['background_path'] = $request->hasFile('background')
            ? \App\Support\TenantStorage::storeUploadedFile($request->file('background'), 'mcq-backgrounds/'.$this->sahodaya->id)
            : null;
        if ($request->hasFile('background')) {
            $size = getimagesize($request->file('background')->getRealPath());
            $normalizedDesign['background_aspect_ratio'] = $size[0] / $size[1];
        }

        McqHallTicketTemplate::create([
            'tenant_id'   => $this->sahodaya->id,
            'title'       => $data['title'],
            'design_json' => $normalizedDesign,
            'is_default'  => $request->boolean('is_default'),
            'is_active'   => true,
        ]);

        return back()->with('success', 'Hall ticket template created.');
    }

    public function updateHallTicket(Request $request, string $tenantId, McqHallTicketTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'        => 'required|string|max:120',
            'is_default'   => 'nullable|boolean',
            'design_json'  => 'nullable|array',
            'background' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if (($data['design_json']['layout'] ?? '') === 'custom_background' && empty($template->design_json['background_path'])) {
            $request->validate(['background' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048']);
        }

        if ($request->boolean('is_default')) {
            McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $normalizedDesign = \App\Support\Mcq\McqHallTicketDesign::normalize($data['design_json'] ?? $template->design_json);
        $normalizedDesign['background_path'] = $request->hasFile('background')
            ? \App\Support\TenantStorage::storeUploadedFile($request->file('background'), 'mcq-backgrounds/'.$this->sahodaya->id)
            : ($template->design_json['background_path'] ?? null);
        if ($request->hasFile('background')) {
            $size = getimagesize($request->file('background')->getRealPath());
            $normalizedDesign['background_aspect_ratio'] = $size[0] / $size[1];
        }

        $template->update([
            'title'       => $data['title'],
            'design_json' => $normalizedDesign,
            'is_default'  => $request->boolean('is_default'),
        ]);

        return back()->with('success', 'Hall ticket template updated.');
    }

    public function destroyHallTicket(string $tenantId, McqHallTicketTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);

        $count = McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)->count();
        if ($count <= 1) {
            return back()->with('error', 'Cannot delete the only hall ticket template.');
        }

        $wasDefault = $template->is_default;
        $template->delete();

        if ($wasDefault) {
            $next = McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)->first();
            $next?->update(['is_default' => true]);
        }

        return back()->with('success', 'Template removed.');
    }

    public function setDefaultHallTicket(string $tenantId, McqHallTicketTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);

        McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)->update(['is_default' => false]);
        $template->update(['is_default' => true]);

        return back()->with('success', "Template '{$template->title}' set as default.");
    }

    public function certificates()
    {
        $templates = McqCertificateTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        return $this->inertia('Sahodaya/Mcq/Templates/Certificates', [
            'templates' => $templates,
        ]);
    }

    public function storeCertificate(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:120',
            'is_default'   => 'nullable|boolean',
            'design_json'  => 'nullable|array',
        ]);

        if ($request->boolean('is_default')) {
            McqCertificateTemplate::where('tenant_id', $this->sahodaya->id)->update(['is_default' => false]);
        }

        McqCertificateTemplate::create([
            'tenant_id'   => $this->sahodaya->id,
            'title'       => $data['title'],
            'design_json' => $data['design_json'] ?? [],
            'is_default'  => $request->boolean('is_default'),
            'is_active'   => true,
        ]);

        return back()->with('success', 'Certificate template saved.');
    }
}
