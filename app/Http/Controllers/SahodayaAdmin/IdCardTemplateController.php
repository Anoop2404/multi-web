<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\IdCardTemplate;
use App\Services\Certificates\CertificateBackgroundConverter;
use App\Services\Events\IdCardTemplatePresetInstaller;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\IdCardDiePreset;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IdCardTemplateController extends SahodayaAdminController
{
    public function index(IdCardTemplatePresetInstaller $presetInstaller)
    {
        $templates = IdCardTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (IdCardTemplate $t) {
                $row = $t->toArray();
                $row['background_url'] = $t->background_path
                    ? TenantStorage::logoUrl($this->sahodaya, $t->background_path)
                    : null;
                $row['layout_json'] = $t->fields();

                return $row;
            });

        $festEvents = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('event_start')
            ->with(['items' => fn ($q) => $q->orderBy('display_order')])
            ->get(['id', 'title', 'event_start'])
            ->map(function (FestEvent $e) {
                $classGroupLabels = FestClassGroupScheme::labels(null, $e->rootEvent());
                $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

                return [
                    'id'    => $e->id,
                    'title' => $e->title,
                    'items' => $e->items->map(fn ($i) => [
                        'id'             => $i->id,
                        'title'          => $i->title,
                        'category_label' => FestItemCategoryLabel::resolve($i, $classGroupLabels, $artsCategoryLabels),
                    ])->values(),
                ];
            });

        return $this->inertia('Sahodaya/IdCardTemplates/Index', [
            'templates'          => $templates,
            'festEvents'         => $festEvents,
            'dataSourceOptions'  => IdCardTemplate::dataSourceOptions(),
            'fontFamilyOptions'  => CertificateTemplate::fontFamilyOptions(),
            'defaultFields'      => IdCardTemplate::defaultFields(),
            'templatePresets'    => $presetInstaller->options(),
            'diePresets'         => IdCardDiePreset::options(),
        ]);
    }

    public function installPreset(
        string $tenantId,
        string $preset,
        IdCardTemplatePresetInstaller $presetInstaller,
    ) {
        abort_unless($tenantId === (string) $this->sahodaya->id, 403);
        abort_unless($presetInstaller->has($preset), 404);

        $template = $presetInstaller->install((string) $this->sahodaya->id, $preset);

        return back()->with(
            'success',
            "{$template->title} created. Preview it, make any adjustments, and activate it when ready.",
        );
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        if (! empty($data['event_id'])) {
            $event = FestEvent::where('id', $data['event_id'])->where('tenant_id', $this->sahodaya->id)->first();
            abort_unless($event, 422, 'Event does not belong to this Sahodaya.');
            if (! empty($data['item_id'])) {
                abort_unless($event->items()->where('id', $data['item_id'])->exists(), 422, 'Item does not belong to the selected event.');
            }
        } elseif (! empty($data['item_id'])) {
            abort(422, 'Select an event before choosing an item.');
        }

        $baseDir = 'sahodaya/'.$this->sahodaya->id.'/id-card-templates';
        $disk = TenantStorage::uploadDisk();

        $backgroundPath = null;
        if ($request->hasFile('background')) {
            $stored = app(CertificateBackgroundConverter::class)
                ->storeFromUpload($request->file('background'), $baseDir, $disk);
            $backgroundPath = $stored['background_path'];
        }

        if (($data['is_active'] ?? true)) {
            $this->deactivateSiblings($data['event_id'] ?? null, $data['item_id'] ?? null, $data['audience'] ?? null);
        }

        IdCardTemplate::create([
            'tenant_id'       => $this->sahodaya->id,
            'event_id'        => $data['event_id'] ?? null,
            'item_id'         => $data['item_id'] ?? null,
            'audience'        => $data['audience'] ?? null,
            'title'           => $data['title'] ?? null,
            'background_path' => $backgroundPath,
            'card_width_mm'   => $data['card_width_mm'] ?? 96,
            'card_height_mm'  => $data['card_height_mm'] ?? 72,
            'cards_per_page'  => $data['cards_per_page'] ?? 4,
            'page_width_mm'   => $data['page_width_mm'] ?? null,
            'page_height_mm'  => $data['page_height_mm'] ?? null,
            'grid_json'       => $this->buildGridJson($data),
            'layout_json'     => $data['fields'] ?? IdCardTemplate::defaultFields(),
            'is_active'       => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'ID card template saved.');
    }

    public function update(Request $request, string $tenantId, IdCardTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);

        $data = $this->validatedData($request, forUpdate: true);

        $baseDir = 'sahodaya/'.$this->sahodaya->id.'/id-card-templates';
        $disk = TenantStorage::uploadDisk();

        // Preserve intentionally-cleared nullable values (title/page size/audience).
        // array_filter() used to silently discard nulls here, so the form appeared to
        // save while "A4 / blank" and "All audiences" never actually persisted.
        $updates = [];
        foreach (['title', 'audience', 'card_width_mm', 'card_height_mm', 'cards_per_page', 'page_width_mm', 'page_height_mm'] as $key) {
            if (array_key_exists($key, $data)) {
                $updates[$key] = $data[$key];
            }
        }

        if (array_key_exists('fields', $data)) {
            $updates['layout_json'] = $data['fields'];
        }

        // Unlike the array_filter()'d fields above, an explicitly-cleared grid (all 6
        // sub-fields left blank, going back to the plain auto-flow table) must persist
        // as null, not be silently dropped — so this is always set directly.
        $updates['grid_json'] = $this->buildGridJson($data);

        if ($request->hasFile('background')) {
            $stored = app(CertificateBackgroundConverter::class)
                ->storeFromUpload($request->file('background'), $baseDir, $disk);
            $updates['background_path'] = $stored['background_path'];
        }

        if (array_key_exists('is_active', $data) && $data['is_active']) {
            $this->deactivateSiblings(
                $template->event_id,
                $template->item_id,
                array_key_exists('audience', $updates) ? $updates['audience'] : $template->audience,
                exceptId: $template->id,
            );
            $updates['is_active'] = true;
        } elseif (array_key_exists('is_active', $data)) {
            $updates['is_active'] = (bool) $data['is_active'];
        }

        $template->update($updates);

        return back()->with('success', 'ID card template updated.');
    }

    public function destroy(string $tenantId, IdCardTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);
        $template->delete();

        return back()->with('success', 'ID card template removed.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, bool $forUpdate = false): array
    {
        $rules = [
            'title'           => 'nullable|string|max:255',
            'event_id'        => 'nullable|integer|exists:fest_events,id',
            'item_id'         => 'nullable|integer|exists:fest_event_items,id',
            'audience'        => ['nullable', Rule::in(['student', 'volunteer', 'staff'])],
            'background'      => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240',
            'card_width_mm'   => 'nullable|integer|min:40|max:150',
            'card_height_mm'  => 'nullable|integer|min:40|max:150',
            'cards_per_page'  => 'nullable|integer|min:1|max:12',
            // Physical print page/sheet size — distinct from the card's own size above.
            // Null (either one) means "use A4 portrait", the size the sheet views have
            // always hardcoded via `@page`.
            'page_width_mm'   => 'nullable|numeric|min:50|max:2000',
            'page_height_mm'  => 'nullable|numeric|min:50|max:2000',
            // Exact die-cut card placement — see IdCardTemplate::gridLayout(). All six
            // must be present (buildGridJson()) for the grid to take effect; any left
            // blank clears it back to the plain auto-flow table.
            'grid_cols'                  => 'nullable|integer|min:1|max:20',
            'grid_rows'                  => 'nullable|integer|min:1|max:20',
            'grid_first_col_center_mm'   => 'nullable|numeric|min:0|max:2000',
            'grid_first_row_center_mm'   => 'nullable|numeric|min:0|max:2000',
            'grid_col_pitch_mm'          => 'nullable|numeric|min:0|max:2000',
            'grid_row_pitch_mm'          => 'nullable|numeric|min:0|max:2000',
            'fields'                  => 'nullable|array',
            'fields.*.key'            => 'nullable|string|max:60',
            'fields.*.type'           => ['nullable', Rule::in(['text', 'photo', 'qr', 'item_list', 'item_row', 'shape', 'static_text', 'divider'])],
            'fields.*.source'         => 'nullable|string|max:60',
            'fields.*.text_format'    => 'nullable|string|max:255',
            'fields.*.top'            => 'nullable|numeric|min:0|max:100',
            'fields.*.left'           => 'nullable|numeric|min:0|max:100',
            'fields.*.width'          => 'nullable|numeric|min:1|max:100',
            'fields.*.height'         => 'nullable|numeric|min:1|max:100',
            'fields.*.font_size'      => 'nullable|integer|min:5|max:48',
            'fields.*.line_height'    => 'nullable|numeric|min:0.8|max:2',
            'fields.*.wrap'           => 'nullable|boolean',
            'fields.*.rotation'       => 'nullable|numeric|min:-360|max:360',
            'fields.*.font_family'    => 'nullable|string|max:40',
            'fields.*.font_weight'    => 'nullable|in:normal,bold',
            'fields.*.font_style'     => 'nullable|in:normal,italic',
            'fields.*.align'          => 'nullable|in:left,right,center',
            // Decorative/static field types — 'shape'/'static_text'/'divider' are
            // template-configured chrome (column labels, ribbons, gradient items
            // header); 'item_row' is a numbered participating-item row.
            'fields.*.row'            => 'nullable|integer|min:1|max:20',
            'fields.*.columns'        => 'nullable|integer|min:1|max:2',
            'fields.*.max_items'      => 'nullable|integer|min:1|max:7',
            'fields.*.text'           => 'nullable|string|max:120',
            'fields.*.color'          => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'fields.*.gradient_from'  => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'fields.*.gradient_to'    => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'fields.*.radius'         => 'nullable|numeric|min:0|max:50',
            'fields.*.orientation'    => 'nullable|in:horizontal,vertical',
            'is_active'       => 'nullable|boolean',
        ];

        if (! $forUpdate) {
            // event/item/audience scope is fixed at creation; not resent on update
        }

        return $request->validate($rules);
    }

    /**
     * Builds the grid_json array from the admin form's six discrete number inputs, or
     * null if any is missing — a half-filled grid isn't a usable placement, and falling
     * back to the plain auto-flow table is safer than guessing at the missing values.
     */
    private function buildGridJson(array $data): ?array
    {
        $keys = ['grid_cols', 'grid_rows', 'grid_first_col_center_mm', 'grid_first_row_center_mm', 'grid_col_pitch_mm', 'grid_row_pitch_mm'];
        foreach ($keys as $key) {
            if (! isset($data[$key]) || $data[$key] === '') {
                return null;
            }
        }

        return [
            'cols'                => (int) $data['grid_cols'],
            'rows'                => (int) $data['grid_rows'],
            'first_col_center_mm' => (float) $data['grid_first_col_center_mm'],
            'first_row_center_mm' => (float) $data['grid_first_row_center_mm'],
            'col_pitch_mm'        => (float) $data['grid_col_pitch_mm'],
            'row_pitch_mm'        => (float) $data['grid_row_pitch_mm'],
        ];
    }

    /**
     * Renders the template with placeholder sample data — for the admin to visually
     * check field placement without needing a real event/registration. ?mode=die
     * repeats the sample card to fill every slot of the die-cut grid at the template's
     * real page size; the default ("single") shows just one card. Reuses the exact
     * same blade the real Generate PDF flow uses, so what's previewed here is what
     * actually prints.
     */
    public function previewSample(Request $request, string $tenantId, IdCardTemplate $template)
    {
        abort_if($template->tenant_id !== $this->sahodaya->id, 403);

        $mode = $request->query('mode') === 'die' ? 'die' : 'single';
        $gridLayout = $mode === 'die' ? $template->gridLayout() : null;
        $count = $gridLayout ? $gridLayout['cols'] * $gridLayout['rows'] : 1;

        $backgroundUrl = $template->background_path
            ? TenantStorage::logoUrl($this->sahodaya, $template->background_path)
            : null;

        return view('fest.id-cards.custom-sheet', [
            'cards'          => array_fill(0, $count, $this->sampleCard()),
            'sections'       => null,
            'clusterName'    => $this->sahodaya->name,
            'clusterLogoSrc' => null,
            'eventTitle'     => 'Sample preview',
            'audience'       => $template->audience ?? 'student',
            'showTitle'      => false,
            'isPdf'          => false,
            'backgroundUrl'  => $backgroundUrl,
            'fields'         => $template->fields(),
            'cardWidthMm'    => $template->card_width_mm,
            'cardHeightMm'   => $template->card_height_mm,
            'cardsPerPage'   => $count,
            'pageWidthMm'    => $mode === 'die' ? $template->page_width_mm : null,
            'pageHeightMm'   => $mode === 'die' ? $template->page_height_mm : null,
            'gridLayout'     => $gridLayout,
        ]);
    }

    /**
     * Same rendering pipeline as previewSample(), but for a template that hasn't been
     * saved yet (or is mid-edit) — the admin form posts its current, unsaved field
     * values here so "preview" works while creating/editing, not only after Save.
     * A newly-uploaded background is used as-is; without one, falls back to the
     * existing template's stored background (edit) so re-previewing doesn't require
     * re-picking the file every time.
     */
    public function previewDraft(Request $request, string $tenantId)
    {
        $data = $this->validatedData($request, forUpdate: true);

        $existing = $request->integer('template_id')
            ? IdCardTemplate::where('tenant_id', $this->sahodaya->id)->find($request->integer('template_id'))
            : null;

        $backgroundPath = $existing?->background_path;
        if ($request->hasFile('background')) {
            $baseDir = 'sahodaya/'.$this->sahodaya->id.'/id-card-templates';
            $disk = TenantStorage::uploadDisk();
            $stored = app(CertificateBackgroundConverter::class)
                ->storeFromUpload($request->file('background'), $baseDir, $disk);
            $backgroundPath = $stored['background_path'];
        }

        $mode = $request->query('mode') === 'die' ? 'die' : 'single';
        $gridJson = $this->buildGridJson($data);
        $gridLayout = $mode === 'die' ? (new IdCardTemplate(['grid_json' => $gridJson]))->gridLayout() : null;
        $count = $gridLayout ? $gridLayout['cols'] * $gridLayout['rows'] : 1;

        $fields = $data['fields'] ?? $existing?->fields() ?? IdCardTemplate::defaultFields();
        $cardWidthMm = $data['card_width_mm'] ?? $existing?->card_width_mm ?? 96;
        $cardHeightMm = $data['card_height_mm'] ?? $existing?->card_height_mm ?? 72;
        $pageWidthMm = $data['page_width_mm'] ?? $existing?->page_width_mm ?? null;
        $pageHeightMm = $data['page_height_mm'] ?? $existing?->page_height_mm ?? null;

        $backgroundUrl = $backgroundPath
            ? TenantStorage::logoUrl($this->sahodaya, $backgroundPath)
            : null;

        return view('fest.id-cards.custom-sheet', [
            'cards'          => array_fill(0, $count, $this->sampleCard()),
            'sections'       => null,
            'clusterName'    => $this->sahodaya->name,
            'clusterLogoSrc' => null,
            'eventTitle'     => 'Draft preview',
            'audience'       => $data['audience'] ?? 'student',
            'showTitle'      => false,
            'isPdf'          => false,
            'backgroundUrl'  => $backgroundUrl,
            'fields'         => $fields,
            'cardWidthMm'    => $cardWidthMm,
            'cardHeightMm'   => $cardHeightMm,
            'cardsPerPage'   => $count,
            'pageWidthMm'    => $mode === 'die' ? $pageWidthMm : null,
            'pageHeightMm'   => $mode === 'die' ? $pageHeightMm : null,
            'gridLayout'     => $gridLayout,
        ]);
    }

    /** @return array<string, mixed> */
    private function sampleCard(): array
    {
        return [
            'name'            => 'LAKSHMI PRIYA VENKATARAMAN NAIR',
            'subtitle'        => 'Sample School Name',
            'detail'          => 'Sample Item Title',
            'item_label'      => 'Sample Item',
            'role_label'      => 'STUDENT',
            'id_number'       => 'SAMPLE-0001',
            'secondary_value' => 'Sample',
            'chest_number'    => '000',
            'category'        => 'III',
            'gender'          => 'Sample',
            'school_code'     => 'ABC-001',
            'student_reg_no'  => 'STU/26/0001',
            'student_seq_id'  => '10203',
            'roll_no'         => '10203',
            'student_class'   => 'X',
            'gender_upper'    => 'FEMALE',
            'student_info_inline' => "CATEGORY: II\u{2003}\u{2003}ROLL NO: 10203\u{2003}\u{2003}GENDER: FEMALE",
            'schedule'        => 'Sample schedule line',
            'footer'          => 'Sample footer',
            'items_inline'    => 'Painting Water Colour | Recitation - Malayalam | Essay Writing Malayalam | Light Music - Malayalam - Girls | Mappillapattu (Boys) (MCS) | Classical Music - Karnatic (Boys)',
            'participating_items' => [
                'Power Point Presentation',
                'Elocution English',
                'Quiz Junior',
                'Painting on the Spot',
                'Group Song Malayalam',
                'Classical Dance Solo',
                'Debate Malayalam',
            ],
            'photo_src'       => $this->samplePhotoDataUri(),
            'qr_src'          => null,
            'item_row_1'      => 'Power Point Presentation',
            'item_row_2'      => 'Elocution English',
            'item_row_3'      => 'Quiz Junior',
            'item_row_4'      => 'Painting on the Spot',
            'item_row_5'      => 'Group Song Malayalam',
            'item_row_6'      => 'Classical Dance Solo',
            'item_row_7'      => 'Debate Malayalam',
        ];
    }

    private function samplePhotoDataUri(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            .'<rect width="100%" height="100%" fill="#d1d5db"/>'
            .'<text x="50%" y="50%" font-family="Arial" font-size="24" fill="#6b7280" text-anchor="middle" dominant-baseline="middle">PHOTO</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function deactivateSiblings(?int $eventId, ?int $itemId, ?string $audience, ?int $exceptId = null): void
    {
        IdCardTemplate::where('tenant_id', $this->sahodaya->id)
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId), fn ($q) => $q->whereNull('event_id'))
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId), fn ($q) => $q->whereNull('item_id'))
            ->when($audience, fn ($q) => $q->where('audience', $audience), fn ($q) => $q->whereNull('audience'))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_active' => false]);
    }
}
