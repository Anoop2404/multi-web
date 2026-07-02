<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\CertificateTemplate;
use Illuminate\Http\Request;

class CertificateTemplateController extends SahodayaAdminController
{
    public function index()
    {
        $templates = CertificateTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderBy('event_type')
            ->get();

        return $this->inertia('Sahodaya/Certificates/Templates', compact('templates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_type'        => 'required|string|max:50',
            'certificate_type'  => 'required|string|max:50',
            'template_file'     => 'required|file|mimes:pdf,png,jpg|max:5120',
            'dynamic_fields_json' => 'nullable|array',
        ]);

        $path = $request->file('template_file')->store(
            'sahodaya/'.$this->sahodaya->id.'/certificate-templates',
            's3'
        );

        $dynamicFields = $data['dynamic_fields_json'] ?? $this->defaultTrainingFields();

        CertificateTemplate::create([
            'tenant_id'           => $this->sahodaya->id,
            'event_type'          => $data['event_type'],
            'certificate_type'    => $data['certificate_type'],
            'template_file_path'  => $path,
            'dynamic_fields_json' => $dynamicFields,
        ]);

        return back()->with('success', 'Template uploaded.');
    }

    public function destroy(string $tenantId, CertificateTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);
        $template->delete();

        return back()->with('success', 'Template removed.');
    }

    /** @return list<array{key: string, source: string, label: string}> */
    private function defaultTrainingFields(): array
    {
        return [
            ['key' => 'recipient_name', 'source' => 'recipient_name', 'label' => 'Recipient name'],
            ['key' => 'program_title', 'source' => 'program_title', 'label' => 'Program title'],
            ['key' => 'sahodaya_name', 'source' => 'sahodaya_name', 'label' => 'Sahodaya name'],
            ['key' => 'conducted_on', 'source' => 'conducted_on', 'label' => 'Conducted on'],
            ['key' => 'designation', 'source' => 'designation', 'label' => 'Designation'],
        ];
    }
}
