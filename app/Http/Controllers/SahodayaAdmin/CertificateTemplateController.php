<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\CertificateTemplate;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class CertificateTemplateController extends SahodayaAdminController
{
    public function index()
    {
        $templates = CertificateTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderBy('event_type')
            ->get();

        return $this->inertia('Sahodaya/Certificates/Templates', [
            'templates'       => $templates,
            'defaultBody'     => CertificateTemplate::defaultTrainingBody(),
            'defaultTopperBody' => CertificateTemplate::defaultTopperBody(),
            'defaultSignatories' => CertificateTemplate::defaultTrainingSignatories(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_type'          => 'required|string|max:50',
            'certificate_type'    => 'required|string|max:50',
            'title'               => 'nullable|string|max:255',
            'body'                => 'nullable|string',
            'template_file'       => 'nullable|file|mimes:pdf,png,jpg|max:5120',
            'logo'                => 'nullable|image|max:2048',
            'seal'                => 'nullable|image|max:2048',
            'signatories'         => 'nullable|array',
            'signatories.*.name'  => 'nullable|string|max:120',
            'signatories.*.designation' => 'nullable|string|max:120',
            'signatories.*.signature' => 'nullable|image|max:1024',
            'dynamic_fields_json' => 'nullable|array',
            'is_active'           => 'nullable|boolean',
        ]);

        $baseDir = 'sahodaya/'.$this->sahodaya->id.'/certificate-templates';
        $disk = TenantStorage::uploadDisk();

        $templatePath = null;
        if ($request->hasFile('template_file')) {
            $templatePath = $request->file('template_file')->store($baseDir, $disk);
        }

        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store($baseDir.'/logos', $disk)
            : null;

        $sealPath = $request->hasFile('seal')
            ? $request->file('seal')->store($baseDir.'/seals', $disk)
            : null;

        $signatories = $this->normalizeSignatories($request, $data['signatories'] ?? null, $baseDir.'/signatures', $disk);

        $dynamicFields = $data['dynamic_fields_json'] ?? $this->defaultTrainingFields();
        $body = $data['body'] ?? match ($data['event_type']) {
            'training' => CertificateTemplate::defaultTrainingBody(),
            'topper' => CertificateTemplate::defaultTopperBody(),
            default => null,
        };

        if ($data['is_active'] ?? true) {
            CertificateTemplate::where('tenant_id', $this->sahodaya->id)
                ->where('event_type', $data['event_type'])
                ->where('certificate_type', $data['certificate_type'])
                ->update(['is_active' => false]);
        }

        CertificateTemplate::create([
            'tenant_id'           => $this->sahodaya->id,
            'event_type'          => $data['event_type'],
            'certificate_type'    => $data['certificate_type'],
            'title'               => $data['title'] ?? 'Certificate of Participation',
            'body'                => $body,
            'template_file_path'  => $templatePath,
            'logo_path'           => $logoPath,
            'seal_path'           => $sealPath,
            'signatories'         => $signatories,
            'dynamic_fields_json' => $dynamicFields,
            'is_active'           => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Template saved.');
    }

    public function update(Request $request, string $tenantId, CertificateTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'               => 'nullable|string|max:255',
            'body'                => 'nullable|string',
            'logo'                => 'nullable|image|max:2048',
            'seal'                => 'nullable|image|max:2048',
            'signatories'         => 'nullable|array',
            'signatories.*.name'  => 'nullable|string|max:120',
            'signatories.*.designation' => 'nullable|string|max:120',
            'signatories.*.signature' => 'nullable|image|max:1024',
            'signatories.*.signature_path' => 'nullable|string',
            'is_active'           => 'nullable|boolean',
        ]);

        $baseDir = 'sahodaya/'.$this->sahodaya->id.'/certificate-templates';
        $disk = TenantStorage::uploadDisk();
        $updates = array_filter([
            'title' => $data['title'] ?? null,
            'body'  => $data['body'] ?? null,
        ], fn ($v) => $v !== null);

        if ($request->hasFile('logo')) {
            $updates['logo_path'] = $request->file('logo')->store($baseDir.'/logos', $disk);
        }
        if ($request->hasFile('seal')) {
            $updates['seal_path'] = $request->file('seal')->store($baseDir.'/seals', $disk);
        }

        if (array_key_exists('signatories', $data)) {
            $updates['signatories'] = $this->normalizeSignatories(
                $request,
                $data['signatories'],
                $baseDir.'/signatures',
                $disk,
                $template->signatories ?? [],
            );
        }

        if (array_key_exists('is_active', $data) && $data['is_active']) {
            CertificateTemplate::where('tenant_id', $this->sahodaya->id)
                ->where('event_type', $template->event_type)
                ->where('certificate_type', $template->certificate_type)
                ->where('id', '!=', $template->id)
                ->update(['is_active' => false]);
            $updates['is_active'] = true;
        } elseif (array_key_exists('is_active', $data)) {
            $updates['is_active'] = (bool) $data['is_active'];
        }

        $template->update($updates);

        return back()->with('success', 'Template updated.');
    }

    public function destroy(string $tenantId, CertificateTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);
        $template->delete();

        return back()->with('success', 'Template removed.');
    }

    /** @return list<array{name: string, designation: string, signature_path: ?string}> */
    private function normalizeSignatories(Request $request, ?array $input, string $dir, string $disk, array $existing = []): array
    {
        if ($input === null) {
            return $existing !== [] ? $existing : CertificateTemplate::defaultTrainingSignatories();
        }

        $out = [];
        foreach ($input as $i => $row) {
            $path = $row['signature_path'] ?? ($existing[$i]['signature_path'] ?? null);
            $file = $request->file("signatories.{$i}.signature");
            if ($file) {
                $path = $file->store($dir, $disk);
            }
            $out[] = [
                'name'            => $row['name'] ?? '',
                'designation'     => $row['designation'] ?? '',
                'signature_path'  => $path,
            ];
        }

        return $out;
    }

    /** @return list<array{key: string, source: string, label: string}> */
    private function defaultTrainingFields(): array
    {
        return [
            ['key' => 'recipient_name', 'source' => 'recipient_name', 'label' => 'Recipient name'],
            ['key' => 'program_title', 'source' => 'program_title', 'label' => 'Program title'],
            ['key' => 'sahodaya_name', 'source' => 'sahodaya_name', 'label' => 'Sahodaya name'],
            ['key' => 'conducted_on', 'source' => 'conducted_on', 'label' => 'Dates attended'],
            ['key' => 'designation', 'source' => 'designation', 'label' => 'Designation'],
            ['key' => 'school_name', 'source' => 'school_name', 'label' => 'School name'],
            ['key' => 'venue', 'source' => 'venue', 'label' => 'Venue'],
            ['key' => 'days_attended', 'source' => 'days_attended', 'label' => 'Days attended'],
            ['key' => 'training_hours', 'source' => 'training_hours', 'label' => 'Training hours'],
            ['key' => 'total_days', 'source' => 'total_days', 'label' => 'Total days'],
            ['key' => 'certificate_date', 'source' => 'certificate_date', 'label' => 'Certificate date'],
        ];
    }
}
