<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemReportingBatch;
use App\Models\FestRegistration;
use App\Models\FestSchoolDistance;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestNumberingService;
use App\Support\FestPageActivity;
use App\Support\PdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestItemReportingBatchController extends SahodayaAdminController
{
    /**
     * Below this many unique registrations, an item runs fine as one group — batching is
     * only offered for items large enough that staggering reporting time actually helps.
     * A Sahodaya can override this per event (FestEvent::reporting_batch_min_registrations);
     * this is only the fallback when they haven't set one.
     */
    private const DEFAULT_MIN_REGISTRATIONS_FOR_BATCHING = 15;

    /**
     * Default "registrations per batch" for auto-assign-by-distance, when the event
     * hasn't set FestEvent::reporting_batch_size.
     */
    private const DEFAULT_BATCH_SIZE = 8;

    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $minRegistrations = $event->reporting_batch_min_registrations ?? self::DEFAULT_MIN_REGISTRATIONS_FOR_BATCHING;
        $batchSize = $event->reporting_batch_size ?? self::DEFAULT_BATCH_SIZE;

        $regCounts = FestRegistration::where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->selectRaw('item_id, count(*) as reg_count')
            ->groupBy('item_id')
            ->pluck('reg_count', 'item_id');

        $assignedCounts = FestRegistration::where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->whereNotNull('reporting_batch_id')
            ->selectRaw('item_id, count(*) as assigned_count')
            ->groupBy('item_id')
            ->pluck('assigned_count', 'item_id');

        // How many of the event's common batches THIS item actually has members in --
        // not the event-wide batch total, which would show the same number on every
        // item even though most batches only hold other items' registrations.
        $usedBatchCounts = FestRegistration::where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->whereNotNull('reporting_batch_id')
            ->select('item_id', 'reporting_batch_id')
            ->distinct()
            ->get()
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->pluck('reporting_batch_id')->unique()->count());

        // "Category" here means the item's class/age bracket (e.g. "Category 1 — Classes
        // 3 & 4"), not the internal arts-genre tag on $item->category -- see
        // FestItemCategoryLabel's docblock. class_group/age_group weren't even being
        // selected before, so this was silently showing the wrong thing (dance/music/
        // drama) instead of the class category admins actually care about here.
        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent());

        $items = $event->items()->orderBy('title')->get(['id', 'title', 'item_code', 'category', 'class_group', 'age_group', 'gender', 'stage_type'])
            ->map(function (FestEventItem $item) use ($regCounts, $assignedCounts, $usedBatchCounts, $classGroupLabels) {
                $regCount = (int) ($regCounts[$item->id] ?? 0);
                $assignedCount = (int) ($assignedCounts[$item->id] ?? 0);

                return [
                    'id'                 => $item->id,
                    'title'              => $item->title,
                    'item_code'          => $item->item_code,
                    'category'           => \App\Support\FestItemCategoryLabel::resolve($item, $classGroupLabels),
                    'gender_label'       => \App\Support\FestSportsAgeGroup::genderLabel($item->gender),
                    'is_group'           => app(FestNumberingService::class)->isGroupItem($item),
                    'registration_count' => $regCount,
                    'batch_count'        => (int) ($usedBatchCounts[$item->id] ?? 0),
                    'assigned_count'     => $assignedCount,
                    'unassigned_count'   => $regCount - $assignedCount,
                ];
            })
            ->filter(fn (array $item) => $item['registration_count'] > $minRegistrations)
            ->values();

        $itemId = $request->integer('item_id') ?: null;
        $selectedItem = null;
        $registrations = [];
        // Batches are a common roster for the whole event, so the list (and the Batch Master
        // modal that manages it) doesn't need an item selected to be useful.
        $batches = $this->batchesForEvent($event);

        if ($itemId) {
            $itemModel = FestEventItem::where('event_id', $event->id)->find($itemId);
            abort_unless($itemModel, 404);

            $selectedItem = $this->itemSummary($itemModel, $items, $regCounts);
            $registrations = $this->registrationRows($event, $itemModel);
        }

        return $this->inertia('Sahodaya/Events/ReportingBatches', $this->withEventActivity($event, FestPageActivity::REPORTING_BATCHES, [
            'event'            => $event,
            'items'            => $items,
            'selectedItem'     => $selectedItem,
            'selectedItemId'   => $itemId,
            'batches'          => $batches,
            'registrations'    => $registrations,
            'minRegistrations' => $minRegistrations,
            'batchSize'        => $batchSize,
        ]));
    }

    public function updateSettings(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'reporting_batch_min_registrations' => 'nullable|integer|min:0',
            'reporting_batch_size'              => 'nullable|integer|min:1',
        ]);

        $event->update([
            'reporting_batch_min_registrations' => $data['reporting_batch_min_registrations'] ?? null,
            'reporting_batch_size'              => $data['reporting_batch_size'] ?? null,
        ]);

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.reporting_batch.settings_updated', 'Updated reporting batch settings', [
            'reporting_batch_min_registrations' => $event->reporting_batch_min_registrations,
            'reporting_batch_size'              => $event->reporting_batch_size,
        ]);

        return back()->with('success', 'Reporting batch settings saved.');
    }

    /** Full-page "batch master" — the same grouped listing as the modal, at its own URL. */
    public function batchMaster(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->integer('item_id');
        abort_unless($itemId, 404);

        $itemModel = FestEventItem::where('event_id', $event->id)->find($itemId);
        abort_unless($itemModel, 404);

        return $this->inertia('Sahodaya/Events/ReportingBatchMaster', [
            'event'         => $event,
            'selectedItem'  => $this->itemSummary($itemModel),
            'batches'       => $this->batchesForEvent($event),
            'registrations' => $this->registrationRows($event, $itemModel),
            'batchSize'     => $event->reporting_batch_size ?? self::DEFAULT_BATCH_SIZE,
        ]);
    }

    public function print(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->integer('item_id');
        abort_unless($itemId, 404);

        $itemModel = FestEventItem::where('event_id', $event->id)->find($itemId);
        abort_unless($itemModel, 404);

        $batches = $this->batchesForEvent($event);
        $orgName = $this->sahodaya->name;
        $logoSrc = \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya);

        $pdfBytes = $this->renderItemReportingBatchesPdf($event, $itemModel, $batches, $orgName, $logoSrc);

        $slug = \Illuminate\Support\Str::slug($itemModel->title ?: 'item');
        $disposition = ($request->boolean('inline') || $request->boolean('preview')) ? 'inline' : 'attachment';

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$slug}-reporting-batches.pdf\"",
        ]);
    }

    /**
     * One PDF covering every qualifying item's reporting-batch sheet. Each item is
     * rendered as its own independent PDF (same as print() above, so it gets that item's
     * own repeating per-page header) and the results are merged page-by-page with FPDI --
     * a single shared document with one Chromium/dompdf header could only ever show one
     * item's name across the whole file, since that header mechanism is global to the
     * page, not to a content block. Rendering N real PDFs and merging them is what makes
     * every page of every item show that item's own name/category/gender/type, including
     * continuation pages when one item's batches span more than one page.
     */
    public function bulkPrint(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $minRegistrations = $event->reporting_batch_min_registrations ?? self::DEFAULT_MIN_REGISTRATIONS_FOR_BATCHING;
        $batches = $this->batchesForEvent($event);

        $regCounts = FestRegistration::where('event_id', $event->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->selectRaw('item_id, count(*) as reg_count')
            ->groupBy('item_id')
            ->pluck('reg_count', 'item_id');

        $items = $event->items()->orderBy('title')->get()
            ->filter(fn (FestEventItem $item) => (int) ($regCounts[$item->id] ?? 0) > $minRegistrations)
            ->values();

        abort_if($items->isEmpty(), 422, 'No items have reporting batches for this event.');

        $orgName = $this->sahodaya->name;
        $logoSrc = \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya);

        $pdfBytesByItem = $items->mapWithKeys(fn (FestEventItem $item) => [
            $item->id => $this->renderItemReportingBatchesPdf($event, $item, $batches, $orgName, $logoSrc),
        ])->all();

        [$merged, $included] = $this->mergePdfByteStrings($pdfBytesByItem, "{$orgName} — {$event->title} — Reporting Batches (All Items)");

        abort_if($included === [], 500, 'None of the items could be merged into a PDF.');

        $slug = \Illuminate\Support\Str::slug($event->title ?: 'event');
        $disposition = ($request->boolean('inline') || $request->boolean('preview')) ? 'inline' : 'attachment';

        return response($merged, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$slug}-reporting-batches-all-items.pdf\"",
        ]);
    }

    /** Renders one item's reporting-batches sheet to raw PDF bytes, with its own repeating per-page header. */
    private function renderItemReportingBatchesPdf(FestEvent $event, FestEventItem $item, \Illuminate\Support\Collection $batches, string $orgName, ?string $logoSrc): string
    {
        $sections = $this->sectionsForItem($event, $item, $batches);
        $categoryLabel = \App\Support\FestItemCategoryLabel::resolve($item, \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent()));
        // Chromium's own header/footer templates render in a reserved margin band, isolated
        // from page content -- the blade's in-content position:fixed div (dompdf's technique)
        // sits inside the same content box the table rows flow into on the Chromium path, so
        // it overlaps the first rows instead of sitting above them. Keep the two mutually
        // exclusive: dompdf gets the in-content div, Chromium gets headerTemplate below.
        $isDomPdf = empty(config('services.pdf_converter.url'));
        $participantCount = collect($sections)->sum(fn ($s) => count($s['rows']));

        $html = view('fest.reporting-batches-print', [
            'event'         => $event,
            'item'          => $item,
            'categoryLabel' => $categoryLabel,
            'isGroup'       => app(FestNumberingService::class)->isGroupItem($item),
            'sections'      => $sections,
            'orgName'       => $orgName,
            'logoSrc'       => $logoSrc,
            'isDomPdf'      => $isDomPdf,
        ])->render();

        [$headerTemplate, $footerTemplate] = \App\Support\PdfChromeHeaderFooter::build([
            'orgName'          => $orgName,
            'logoSrc'          => $logoSrc,
            'docTitle'         => 'REPORTING BATCHES',
            'eventTitle'       => $event->title,
            'item'             => $item,
            'categoryLabel'    => $categoryLabel,
            'participantCount' => $participantCount > 0 ? $participantCount : null,
        ]);

        return PdfGenerator::render(
            $html,
            false,
            $headerTemplate,
            $footerTemplate,
            ['top' => '38mm', 'right' => '10mm', 'bottom' => '14mm', 'left' => '10mm'],
        );
    }

    /**
     * Appends every page of every given PDF (as raw bytes) into one output PDF, in order
     * -- same FPDI merge pattern as FestMarkEntryController::mergePdfByteStrings().
     *
     * @param  array<int, string>  $pdfBytesByItemId
     * @return array{0: string, 1: list<int>} [merged PDF bytes, the item ids that actually made it in]
     */
    private function mergePdfByteStrings(array $pdfBytesByItemId, ?string $title = null): array
    {
        $pdf = new \setasign\Fpdi\Fpdi();
        if ($title) {
            $pdf->SetTitle($title, true);
        }
        $included = [];

        foreach ($pdfBytesByItemId as $itemId => $bytes) {
            $tmpPath = tempnam(sys_get_temp_dir(), 'fpdi_');
            file_put_contents($tmpPath, $bytes);

            try {
                $pageCount = $pdf->setSourceFile($tmpPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $templateId = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($templateId);
                    $orientation = ($size['orientation'] ?? 'P') === 'L' ? 'L' : 'P';
                    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
                $included[] = $itemId;
            } catch (\Throwable $e) {
                report(new \RuntimeException("bulkPrint: could not import item {$itemId} into the merge — {$e->getMessage()}", previous: $e));
            } finally {
                @unlink($tmpPath);
            }
        }

        return [$included === [] ? '' : $pdf->Output('S'), $included];
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'label'      => 'required|string|max:255',
            'report_at'  => 'nullable|date',
            'sort_order' => 'nullable|integer',
        ]);

        $data['event_id'] = $event->id;
        $data['sort_order'] = $data['sort_order'] ?? ((int) FestItemReportingBatch::where('event_id', $event->id)->max('sort_order') + 1);

        $batch = FestItemReportingBatch::create($data);

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.reporting_batch.created', "Created reporting batch {$batch->label}", [
            'batch_id' => $batch->id,
        ]);

        return back()->with('success', "Batch '{$batch->label}' created.");
    }

    public function update(Request $request, string $tenantId, FestEvent $event, FestItemReportingBatch $batch, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($batch->event_id !== $event->id, 403);

        $data = $request->validate([
            'label'      => 'sometimes|required|string|max:255',
            'report_at'  => 'nullable|date',
            'sort_order' => 'nullable|integer',
        ]);

        $batch->update($data);

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.reporting_batch.updated', "Updated reporting batch {$batch->label}", [
            'batch_id' => $batch->id,
        ]);

        return back()->with('success', "Batch '{$batch->label}' updated.");
    }

    public function destroy(string $tenantId, FestEvent $event, FestItemReportingBatch $batch, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($batch->event_id !== $event->id, 403);

        $name = $batch->label;
        $batch->delete();

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.reporting_batch.deleted', "Deleted reporting batch {$name}", [
            'batch_id' => $batch->id,
        ]);

        return back()->with('success', "Batch '{$name}' deleted.");
    }

    public function assignRegistrations(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id'          => ['required', 'integer', Rule::exists('fest_event_items', 'id')->where('event_id', $event->id)],
            'batch_id'         => ['nullable', 'integer', Rule::exists('fest_item_reporting_batches', 'id')->where('event_id', $event->id)],
            'registration_ids' => 'required|array',
            'registration_ids.*' => ['integer', Rule::exists('fest_registrations', 'id')->where('event_id', $event->id)->where('item_id', $request->input('item_id'))],
        ]);

        $count = FestRegistration::where('event_id', $event->id)
            ->where('item_id', $data['item_id'])
            ->whereIn('id', $data['registration_ids'])
            ->update(['reporting_batch_id' => $data['batch_id'] ?? null]);

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.reporting_batch.registrations_assigned', "Assigned {$count} registration(s) to reporting batch", [
            'batch_id' => $data['batch_id'] ?? null,
            'item_id'  => $data['item_id'],
            'count'    => $count,
        ]);

        return back()->with('success', "Assigned {$count} registration(s) to batch.");
    }

    /**
     * Splits one item's registrations into batches of N (closest school first), reusing
     * the event's existing common batches in sort_order before creating new ones. This
     * re-assigns every one of the item's registrations -- including ones already
     * manually assigned -- so the result is a clean, predictable re-partition.
     */
    public function autoAssign(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id'    => ['required', 'integer', Rule::exists('fest_event_items', 'id')->where('event_id', $event->id)],
            'batch_size' => 'nullable|integer|min:1',
        ]);

        $itemModel = FestEventItem::where('event_id', $event->id)->findOrFail($data['item_id']);
        $batchSize = $data['batch_size'] ?? ($event->reporting_batch_size ?? self::DEFAULT_BATCH_SIZE);

        // registrationRows() already sorts closest-school-first (falling back to school
        // name, then id) -- the same order the manual "Move to batch" dropdown shows.
        $registrationIds = collect($this->registrationRows($event, $itemModel))->pluck('id')->all();

        if (empty($registrationIds)) {
            return back()->with('success', 'No registrations to assign.');
        }

        $existingBatches = $this->batchesForEvent($event)->values();
        $chunks = array_chunk($registrationIds, $batchSize);
        $nextSortOrder = ((int) FestItemReportingBatch::where('event_id', $event->id)->max('sort_order')) + 1;
        $batchIds = [];

        foreach ($chunks as $index => $chunk) {
            $batch = $existingBatches->get($index);

            if (! $batch) {
                $batch = FestItemReportingBatch::create([
                    'event_id'   => $event->id,
                    'label'      => 'Batch '.($index + 1),
                    'sort_order' => $nextSortOrder++,
                ]);
            }

            FestRegistration::where('event_id', $event->id)
                ->where('item_id', $itemModel->id)
                ->whereIn('id', $chunk)
                ->update(['reporting_batch_id' => $batch->id]);

            $batchIds[] = $batch->id;
        }

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.reporting_batch.auto_assigned', "Auto-assigned {$itemModel->title} into ".count($chunks).' batch(es) by distance', [
            'item_id'    => $itemModel->id,
            'batch_size' => $batchSize,
            'batch_ids'  => $batchIds,
            'count'      => count($registrationIds),
        ]);

        return back()->with('success', 'Auto-assigned '.count($registrationIds).' registration(s) into '.count($chunks).' batch(es), closest schools first.');
    }

    /**
     * One item's registrations grouped into printable sections: one per common batch (in
     * sort_order) that this item actually has members in, plus a trailing "Unassigned"
     * section when applicable. Batches other items use but this one doesn't are left out
     * entirely -- the event may have many common batches, and printing an empty "0
     * registrations" table for each of them on every item's report is just noise. Shared
     * by the single-item and bulk (all-items) print actions.
     *
     * @return list<array{label: string, report_at: ?string, rows: list<array<string, mixed>>}>
     */
    private function sectionsForItem(FestEvent $event, FestEventItem $item, \Illuminate\Support\Collection $batches): array
    {
        $registrations = $this->registrationRows($event, $item);
        $grouped = collect($registrations)->groupBy(fn ($row) => $row['reporting_batch_id'] ?? 'unassigned');

        $sections = $batches->map(fn (FestItemReportingBatch $batch) => [
            'label'     => $batch->label,
            'report_at' => $batch->report_at,
            'rows'      => ($grouped[$batch->id] ?? collect())->values()->all(),
        ])->filter(fn (array $section) => count($section['rows']) > 0)->values()->all();

        $unassignedRows = ($grouped['unassigned'] ?? collect())->values()->all();
        if (! empty($unassignedRows)) {
            $sections[] = ['label' => 'Unassigned', 'report_at' => null, 'rows' => $unassignedRows];
        }

        return $sections;
    }

    /** @return list<array<string, mixed>> */
    private function registrationRows(FestEvent $event, FestEventItem $item): array
    {
        $isGroupItem = app(FestNumberingService::class)->isGroupItem($item);

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->with(['school', 'group', 'participants.student', 'participants.teacher', 'reportingBatch'])
            ->get();

        // Nullable so we can sort distance-set schools first (closest km ascending) and push
        // everything else after, still in the original alphabetical-by-school order.
        $distances = FestSchoolDistance::where('event_id', $event->id)
            ->whereIn('school_id', $registrations->pluck('school_id')->unique())
            ->pluck('distance_km', 'school_id');

        return $registrations
            ->map(function (FestRegistration $reg) use ($isGroupItem, $distances) {
                $school = $reg->school?->name ?? Tenant::find($reg->school_id)?->name;

                $firstParticipantName = null;
                if ($isGroupItem) {
                    $group = $reg->group;
                    $name = $group?->team_name ?: 'Team';
                    $firstParticipant = $reg->participants->sortBy('order_no')->first();
                    $firstParticipantName = $firstParticipant?->student?->name ?? $firstParticipant?->teacher?->name;
                } else {
                    $participant = $reg->participants->first();
                    $name = $participant?->student?->name ?? $participant?->teacher?->name ?? 'Participant';
                }

                return [
                    'id'                    => $reg->id,
                    'name'                  => $name,
                    'first_participant_name' => $firstParticipantName,
                    'is_team'               => $isGroupItem,
                    'member_count'          => $isGroupItem ? $reg->participants->count() : 1,
                    'school'                => $school,
                    'distance_km'           => isset($distances[$reg->school_id]) ? (float) $distances[$reg->school_id] : null,
                    'status'                => $reg->status,
                    'reporting_batch_id'    => $reg->reporting_batch_id,
                    'batch_label'           => $reg->reportingBatch?->label,
                ];
            })
            ->sortBy(fn ($row) => [$row['distance_km'] ?? PHP_FLOAT_MAX, $row['school'] ?? '', $row['id']])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function itemSummary(FestEventItem $item, ?\Illuminate\Support\Collection $items = null, ?\Illuminate\Support\Collection $regCounts = null): array
    {
        $fromFilteredList = $items?->firstWhere('id', $item->id);
        if ($fromFilteredList) {
            return $fromFilteredList;
        }

        $count = $regCounts !== null
            ? (int) ($regCounts[$item->id] ?? 0)
            : FestRegistration::where('item_id', $item->id)->whereNotIn('status', ['rejected', 'withdrawn'])->count();

        $assigned = FestRegistration::where('item_id', $item->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->whereNotNull('reporting_batch_id')
            ->count();

        $usedBatchCount = FestRegistration::where('item_id', $item->id)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->whereNotNull('reporting_batch_id')
            ->distinct('reporting_batch_id')
            ->count('reporting_batch_id');

        $classGroupLabels = \App\Support\FestClassGroupScheme::labels(null, $item->event->rootEvent());

        return [
            'id'                 => $item->id,
            'title'              => $item->title,
            'item_code'          => $item->item_code,
            'category'           => \App\Support\FestItemCategoryLabel::resolve($item, $classGroupLabels),
            'gender_label'       => \App\Support\FestSportsAgeGroup::genderLabel($item->gender),
            'is_group'           => app(FestNumberingService::class)->isGroupItem($item),
            'registration_count' => $count,
            'batch_count'        => $usedBatchCount,
            'assigned_count'     => $assigned,
            'unassigned_count'   => $count - $assigned,
        ];
    }

    private function batchesForEvent(FestEvent $event): \Illuminate\Support\Collection
    {
        return FestItemReportingBatch::where('event_id', $event->id)
            ->withCount('registrations')
            ->orderBy('sort_order')
            ->get();
    }
}
