<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Services\Certificates\CertificateBackgroundConverter;
use App\Services\Training\TrainingCertificateService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CertificateTemplateController extends SahodayaAdminController
{
    public function index()
    {
        $templates = CertificateTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderBy('event_type')
            ->get()
            ->map(function (CertificateTemplate $t) {
                $row = $t->toArray();
                $row['background_url'] = $t->background_path
                    ? TenantStorage::logoUrl($this->sahodaya, $t->background_path)
                    : null;
                $row['logo_url'] = $t->logo_path
                    ? TenantStorage::logoUrl($this->sahodaya, $t->logo_path)
                    : null;
                $row['seal_url'] = $t->seal_path
                    ? TenantStorage::logoUrl($this->sahodaya, $t->seal_path)
                    : null;
                $row['signatories'] = collect($t->signatories ?? [])->map(fn ($s) => array_merge($s, [
                    'signature_url' => ! empty($s['signature_path'])
                        ? TenantStorage::logoUrl($this->sahodaya, $s['signature_path'])
                        : null,
                ]))->all();

                return $row;
            });

        $festEvents = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('event_start')
            ->with(['items' => fn ($q) => $q->orderBy('display_order')])
            ->get(['id', 'title', 'event_type', 'event_start'])
            ->map(fn (FestEvent $e) => [
                'id'    => $e->id,
                'title' => $e->title,
                'items' => $e->items->map(fn ($i) => ['id' => $i->id, 'title' => $i->title])->values(),
            ]);

        return $this->inertia('Sahodaya/Certificates/Templates', [
            'templates'          => $templates,
            'festEvents'         => $festEvents,
            'defaultBody'        => CertificateTemplate::defaultTrainingBody(),
            'defaultTopperBody'  => CertificateTemplate::defaultTopperBody(),
            'defaultFestBody'    => CertificateTemplate::defaultFestBody(),
            'defaultSignatories' => CertificateTemplate::defaultTrainingSignatories(),
            'defaultLayout'      => CertificateTemplate::defaultBackgroundLayout(),
            'fontFamilyOptions'  => CertificateTemplate::fontFamilyOptions(),
        ]);
    }

    public function preview(string $tenantId, CertificateTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);

        if ($template->event_type === 'training') {
            $render = app(TrainingCertificateService::class)
                ->sampleRenderContextForTemplate($template, $this->sahodaya);

            return view('training.certificate', array_merge($render, [
                'registration' => null,
                'sahodaya'     => $this->sahodaya,
                'isSample'     => true,
            ]));
        }

        $render = app(TrainingCertificateService::class)
            ->sampleRenderContextForTemplate($template, $this->sahodaya);

        return view('training.certificate', array_merge($render, [
            'registration' => null,
            'sahodaya'     => $this->sahodaya,
            'isSample'     => true,
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_type'          => 'required|string|max:50',
            'event_id'            => 'nullable|integer|exists:fest_events,id',
            'item_id'             => 'nullable|integer|exists:fest_event_items,id',
            'certificate_type'    => 'required|string|max:50',
            'title'               => 'nullable|string|max:255',
            'body'                => 'nullable|string',
            'template_file'       => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240',
            'logo'                => 'nullable|image|max:2048',
            'seal'                => 'nullable|image|max:2048',
            'signatories'         => 'nullable|array',
            'signatories.*.name'  => 'nullable|string|max:120',
            'signatories.*.designation' => 'nullable|string|max:120',
            'signatories.*.signature' => 'nullable|image|max:1024',
            'dynamic_fields_json' => 'nullable|array',
            'layout_json'         => 'nullable|array',
            'layout_json.show_recipient_name' => 'nullable|boolean',
            'layout_json.show_participation_label' => 'nullable|boolean',
            'layout_json.bold_variables' => 'nullable|boolean',
            'layout_json.recipient_name.top' => 'nullable|numeric|min:0|max:100',
            'layout_json.recipient_name.font_size' => 'nullable|integer|min:6|max:96',
            'layout_json.recipient_name.font_family' => ['nullable', 'string', Rule::in(CertificateTemplate::fontFamilyOptions())],
            'layout_json.recipient_name.font_weight' => 'nullable|in:normal,bold',
            'layout_json.recipient_name.font_style' => 'nullable|in:normal,italic',
            'layout_json.body.top' => 'nullable|numeric|min:0|max:100',
            'layout_json.body.font_size' => 'nullable|integer|min:6|max:96',
            'layout_json.body.font_family' => ['nullable', 'string', Rule::in(CertificateTemplate::fontFamilyOptions())],
            'layout_json.body.font_weight' => 'nullable|in:normal,bold',
            'layout_json.body.font_style' => 'nullable|in:normal,italic',
            'layout_json.certificate_date.top' => 'nullable|numeric|min:0|max:100',
            'layout_json.certificate_date.left' => 'nullable|numeric|min:0|max:100',
            'layout_json.certificate_date.width' => 'nullable|numeric|min:0|max:100',
            'layout_json.certificate_date.font_size' => 'nullable|integer|min:6|max:96',
            'layout_json.certificate_date.font_family' => ['nullable', 'string', Rule::in(CertificateTemplate::fontFamilyOptions())],
            'layout_json.certificate_date.font_weight' => 'nullable|in:normal,bold',
            'layout_json.certificate_date.font_style' => 'nullable|in:normal,italic',
            'layout_json.certificate_date.align' => 'nullable|in:left,right,center',
            'layout_json.participation_label_cover.top' => 'nullable|numeric|min:0|max:100',
            'layout_json.participation_label_cover.height' => 'nullable|numeric|min:1|max:30',
            'is_active'           => 'nullable|boolean',
        ]);

        if (! empty($data['event_id'])) {
            $event = FestEvent::where('id', $data['event_id'])->where('tenant_id', $this->sahodaya->id)->first();
            abort_unless($event, 422, 'Event does not belong to this Sahodaya.');
            if (! empty($data['item_id'])) {
                abort_unless($event->items()->where('id', $data['item_id'])->exists(), 422, 'Item does not belong to the selected event.');
            }
        } elseif (! empty($data['item_id'])) {
            abort(422, 'Select an event before choosing an item.');
        }

        $baseDir = 'sahodaya/'.$this->sahodaya->id.'/certificate-templates';
        $disk = TenantStorage::uploadDisk();

        $templatePath = null;
        $backgroundPath = null;
        if ($request->hasFile('template_file')) {
            $stored = app(CertificateBackgroundConverter::class)
                ->storeFromUpload($request->file('template_file'), $baseDir, $disk);
            $templatePath = $stored['template_file_path'];
            $backgroundPath = $stored['background_path'];
        }

        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store($baseDir.'/logos', $disk)
            : null;

        $sealPath = $request->hasFile('seal')
            ? $request->file('seal')->store($baseDir.'/seals', $disk)
            : null;

        $signatories = $this->normalizeSignatories($request, $data['signatories'] ?? null, $baseDir.'/signatures', $disk);

        $dynamicFields = $data['dynamic_fields_json'] ?? match ($data['event_type']) {
            'fest' => $this->defaultFestFields(),
            default => $this->defaultTrainingFields(),
        };
        $body = $data['body'] ?? match ($data['event_type']) {
            'training' => CertificateTemplate::defaultTrainingBody(),
            'topper' => CertificateTemplate::defaultTopperBody(),
            'fest' => CertificateTemplate::defaultFestBody(),
            default => null,
        };

        $layout = null;
        if ($backgroundPath || (in_array($data['event_type'], ['training', 'fest'], true) && isset($data['layout_json']))) {
            $layout = $this->mergeLayout($data['layout_json'] ?? null);
        }

        if ($data['is_active'] ?? true) {
            CertificateTemplate::where('tenant_id', $this->sahodaya->id)
                ->where('event_type', $data['event_type'])
                ->where('certificate_type', $data['certificate_type'])
                ->when(! empty($data['event_id']), fn ($q) => $q->where('event_id', $data['event_id']), fn ($q) => $q->whereNull('event_id'))
                ->when(! empty($data['item_id']), fn ($q) => $q->where('item_id', $data['item_id']), fn ($q) => $q->whereNull('item_id'))
                ->update(['is_active' => false]);
        }

        CertificateTemplate::create([
            'tenant_id'           => $this->sahodaya->id,
            'event_type'          => $data['event_type'],
            'event_id'            => $data['event_id'] ?? null,
            'item_id'             => $data['item_id'] ?? null,
            'certificate_type'    => $data['certificate_type'],
            'title'               => $data['title'] ?? 'Certificate of Participation',
            'body'                => $body,
            'template_file_path'  => $templatePath,
            'background_path'     => $backgroundPath,
            'logo_path'           => $logoPath,
            'seal_path'           => $sealPath,
            'signatories'         => $signatories,
            'dynamic_fields_json' => $dynamicFields,
            'layout_json'         => $layout,
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
            'template_file'       => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240',
            'logo'                => 'nullable|image|max:2048',
            'seal'                => 'nullable|image|max:2048',
            'signatories'         => 'nullable|array',
            'signatories.*.name'  => 'nullable|string|max:120',
            'signatories.*.designation' => 'nullable|string|max:120',
            'signatories.*.signature' => 'nullable|image|max:1024',
            'signatories.*.signature_path' => 'nullable|string',
            'layout_json'         => 'nullable|array',
            'layout_json.show_recipient_name' => 'nullable|boolean',
            'layout_json.show_participation_label' => 'nullable|boolean',
            'layout_json.bold_variables' => 'nullable|boolean',
            'layout_json.recipient_name.top' => 'nullable|numeric|min:0|max:100',
            'layout_json.recipient_name.font_size' => 'nullable|integer|min:6|max:96',
            'layout_json.recipient_name.font_family' => ['nullable', 'string', Rule::in(CertificateTemplate::fontFamilyOptions())],
            'layout_json.recipient_name.font_weight' => 'nullable|in:normal,bold',
            'layout_json.recipient_name.font_style' => 'nullable|in:normal,italic',
            'layout_json.body.top' => 'nullable|numeric|min:0|max:100',
            'layout_json.body.font_size' => 'nullable|integer|min:6|max:96',
            'layout_json.body.font_family' => ['nullable', 'string', Rule::in(CertificateTemplate::fontFamilyOptions())],
            'layout_json.body.font_weight' => 'nullable|in:normal,bold',
            'layout_json.body.font_style' => 'nullable|in:normal,italic',
            'layout_json.certificate_date.top' => 'nullable|numeric|min:0|max:100',
            'layout_json.certificate_date.left' => 'nullable|numeric|min:0|max:100',
            'layout_json.certificate_date.width' => 'nullable|numeric|min:0|max:100',
            'layout_json.certificate_date.font_size' => 'nullable|integer|min:6|max:96',
            'layout_json.certificate_date.font_family' => ['nullable', 'string', Rule::in(CertificateTemplate::fontFamilyOptions())],
            'layout_json.certificate_date.font_weight' => 'nullable|in:normal,bold',
            'layout_json.certificate_date.font_style' => 'nullable|in:normal,italic',
            'layout_json.certificate_date.align' => 'nullable|in:left,right,center',
            'layout_json.participation_label_cover.top' => 'nullable|numeric|min:0|max:100',
            'layout_json.participation_label_cover.height' => 'nullable|numeric|min:1|max:30',
            'is_active'           => 'nullable|boolean',
        ]);

        $baseDir = 'sahodaya/'.$this->sahodaya->id.'/certificate-templates';
        $disk = TenantStorage::uploadDisk();
        $updates = array_filter([
            'title' => $data['title'] ?? null,
            'body'  => $data['body'] ?? null,
        ], fn ($v) => $v !== null);

        if ($request->hasFile('template_file')) {
            $stored = app(CertificateBackgroundConverter::class)
                ->storeFromUpload($request->file('template_file'), $baseDir, $disk);
            $updates['template_file_path'] = $stored['template_file_path'];
            $updates['background_path'] = $stored['background_path'];
            if (! isset($data['layout_json']) && ! $template->layout_json) {
                $updates['layout_json'] = CertificateTemplate::defaultBackgroundLayout();
            }
        }

        if ($request->hasFile('logo')) {
            $updates['logo_path'] = $request->file('logo')->store($baseDir.'/logos', $disk);
        }
        if ($request->hasFile('seal')) {
            $updates['seal_path'] = $request->file('seal')->store($baseDir.'/seals', $disk);
        }

        if (array_key_exists('layout_json', $data)) {
            $updates['layout_json'] = $this->mergeLayout($data['layout_json'], $template->layout_json);
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
                ->when($template->event_id, fn ($q) => $q->where('event_id', $template->event_id), fn ($q) => $q->whereNull('event_id'))
                ->when($template->item_id, fn ($q) => $q->where('item_id', $template->item_id), fn ($q) => $q->whereNull('item_id'))
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

    /**
     * @param  array<string, mixed>|null  $input
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    private function mergeLayout(?array $input, ?array $existing = null): array
    {
        $layout = $existing ?: CertificateTemplate::defaultBackgroundLayout();
        if (! is_array($layout)) {
            $layout = CertificateTemplate::defaultBackgroundLayout();
        }
        $layout = array_merge(CertificateTemplate::defaultBackgroundLayout(), $layout);

        if (! is_array($input)) {
            return $layout;
        }

        foreach (['show_recipient_name', 'show_participation_label', 'bold_variables'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $layout[$flag] = filter_var($input[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (['recipient_name', 'body', 'certificate_date', 'uuid', 'participation_label_cover'] as $key) {
            if (! isset($input[$key]) || ! is_array($input[$key])) {
                continue;
            }
            $textKeys = ['top', 'left', 'width', 'font_size', 'font_family', 'font_weight', 'font_style'];
            $allowed = match ($key) {
                'participation_label_cover' => ['top', 'left', 'width', 'height'],
                'certificate_date' => [...$textKeys, 'align'],
                default => $textKeys,
            };
            $layout[$key] = array_merge($layout[$key] ?? [], array_intersect_key(
                $input[$key],
                array_flip($allowed),
            ));
        }

        return $layout;
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

    /** @return list<array{key: string, source: string, label: string}> */
    private function defaultFestFields(): array
    {
        return [
            ['key' => 'recipient_name', 'source' => 'recipient_name', 'label' => 'Recipient name'],
            ['key' => 'school_name', 'source' => 'school_name', 'label' => 'School name'],
            ['key' => 'event_title', 'source' => 'event_title', 'label' => 'Event title'],
            ['key' => 'item_title', 'source' => 'item_title', 'label' => 'Item title'],
            ['key' => 'event_dates', 'source' => 'event_dates', 'label' => 'Event dates'],
            ['key' => 'achievement_line', 'source' => 'achievement_line', 'label' => 'Achievement line'],
            ['key' => 'sahodaya_name', 'source' => 'sahodaya_name', 'label' => 'Sahodaya name'],
            ['key' => 'certificate_date', 'source' => 'certificate_date', 'label' => 'Certificate date'],
        ];
    }
}
