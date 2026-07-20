<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\IdCardTemplate;
use App\Services\Certificates\CertificateBackgroundConverter;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IdCardTemplateController extends SahodayaAdminController
{
    public function index()
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
            ->map(fn (FestEvent $e) => [
                'id'    => $e->id,
                'title' => $e->title,
                'items' => $e->items->map(fn ($i) => ['id' => $i->id, 'title' => $i->title])->values(),
            ]);

        return $this->inertia('Sahodaya/IdCardTemplates/Index', [
            'templates'          => $templates,
            'festEvents'         => $festEvents,
            'dataSourceOptions'  => IdCardTemplate::dataSourceOptions(),
            'defaultFields'      => IdCardTemplate::defaultFields(),
        ]);
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

        $updates = array_filter([
            'title'          => $data['title'] ?? null,
            'card_width_mm'  => $data['card_width_mm'] ?? null,
            'card_height_mm' => $data['card_height_mm'] ?? null,
            'cards_per_page' => $data['cards_per_page'] ?? null,
        ], fn ($v) => $v !== null);

        if (array_key_exists('fields', $data)) {
            $updates['layout_json'] = $data['fields'];
        }

        if ($request->hasFile('background')) {
            $stored = app(CertificateBackgroundConverter::class)
                ->storeFromUpload($request->file('background'), $baseDir, $disk);
            $updates['background_path'] = $stored['background_path'];
        }

        if (array_key_exists('is_active', $data) && $data['is_active']) {
            $this->deactivateSiblings($template->event_id, $template->item_id, $template->audience, exceptId: $template->id);
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
            'fields'                  => 'nullable|array',
            'fields.*.key'            => 'nullable|string|max:60',
            'fields.*.type'           => ['nullable', Rule::in(['text', 'photo', 'qr'])],
            'fields.*.source'         => 'nullable|string|max:60',
            'fields.*.top'            => 'nullable|numeric|min:0|max:100',
            'fields.*.left'           => 'nullable|numeric|min:0|max:100',
            'fields.*.width'          => 'nullable|numeric|min:1|max:100',
            'fields.*.height'         => 'nullable|numeric|min:1|max:100',
            'fields.*.font_size'      => 'nullable|integer|min:5|max:48',
            'fields.*.font_family'    => 'nullable|string|max:40',
            'fields.*.font_weight'    => 'nullable|in:normal,bold',
            'fields.*.font_style'     => 'nullable|in:normal,italic',
            'fields.*.align'          => 'nullable|in:left,right,center',
            'is_active'       => 'nullable|boolean',
        ];

        if (! $forUpdate) {
            // event/item/audience scope is fixed at creation; not resent on update
        }

        return $request->validate($rules);
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
