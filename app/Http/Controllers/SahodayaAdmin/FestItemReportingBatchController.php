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

    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $minRegistrations = $event->reporting_batch_min_registrations ?? self::DEFAULT_MIN_REGISTRATIONS_FOR_BATCHING;

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

        $batchCounts = FestItemReportingBatch::where('event_id', $event->id)
            ->selectRaw('item_id, count(*) as batch_count')
            ->groupBy('item_id')
            ->pluck('batch_count', 'item_id');

        $items = $event->items()->orderBy('title')->get(['id', 'title', 'item_code', 'category', 'stage_type'])
            ->map(function (FestEventItem $item) use ($regCounts, $assignedCounts, $batchCounts) {
                $regCount = (int) ($regCounts[$item->id] ?? 0);
                $assignedCount = (int) ($assignedCounts[$item->id] ?? 0);

                return [
                    'id'                 => $item->id,
                    'title'              => $item->title,
                    'item_code'          => $item->item_code,
                    'category'           => $item->category,
                    'is_group'           => app(FestNumberingService::class)->isGroupItem($item),
                    'registration_count' => $regCount,
                    'batch_count'        => (int) ($batchCounts[$item->id] ?? 0),
                    'assigned_count'     => $assignedCount,
                    'unassigned_count'   => $regCount - $assignedCount,
                ];
            })
            ->filter(fn (array $item) => $item['registration_count'] > $minRegistrations)
            ->values();

        $itemId = $request->integer('item_id') ?: null;
        $selectedItem = null;
        $batches = [];
        $registrations = [];

        if ($itemId) {
            $itemModel = FestEventItem::where('event_id', $event->id)->find($itemId);
            abort_unless($itemModel, 404);

            $selectedItem = $this->itemSummary($itemModel, $items, $regCounts);
            $batches = $this->batchesForItem($event, $itemId);
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
        ]));
    }

    public function updateSettings(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'reporting_batch_min_registrations' => 'nullable|integer|min:0',
        ]);

        $event->update(['reporting_batch_min_registrations' => $data['reporting_batch_min_registrations'] ?? null]);

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.reporting_batch.settings_updated', 'Updated reporting batch settings', [
            'reporting_batch_min_registrations' => $event->reporting_batch_min_registrations,
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
            'batches'       => $this->batchesForItem($event, $itemId),
            'registrations' => $this->registrationRows($event, $itemModel),
        ]);
    }

    public function print(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->integer('item_id');
        abort_unless($itemId, 404);

        $itemModel = FestEventItem::where('event_id', $event->id)->find($itemId);
        abort_unless($itemModel, 404);

        $batches = $this->batchesForItem($event, $itemId);
        $registrations = $this->registrationRows($event, $itemModel);

        $grouped = collect($registrations)->groupBy(fn ($row) => $row['reporting_batch_id'] ?? 'unassigned');

        $sections = $batches->map(fn (FestItemReportingBatch $batch) => [
            'label'    => $batch->label,
            'report_at' => $batch->report_at,
            'rows'     => ($grouped[$batch->id] ?? collect())->values()->all(),
        ])->values()->all();

        $unassignedRows = ($grouped['unassigned'] ?? collect())->values()->all();
        if (! empty($unassignedRows)) {
            $sections[] = ['label' => 'Unassigned', 'report_at' => null, 'rows' => $unassignedRows];
        }

        $orgName = $this->sahodaya->name;
        $logoSrc = \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya);
        $isDomPdf = empty(config('services.pdf_converter.url'));
        $participantCount = collect($sections)->sum(fn ($s) => count($s['rows']));

        $html = view('fest.reporting-batches-print', [
            'event'    => $event,
            'item'     => $itemModel,
            'isGroup'  => app(FestNumberingService::class)->isGroupItem($itemModel),
            'sections' => $sections,
            'orgName'  => $orgName,
            'logoSrc'  => $logoSrc,
            'isDomPdf' => $isDomPdf,
        ])->render();

        $slug = \Illuminate\Support\Str::slug($itemModel->title ?: 'item');
        $inline = $request->boolean('inline') || $request->boolean('preview');

        [$headerTemplate, $footerTemplate] = \App\Support\PdfChromeHeaderFooter::build([
            'orgName'          => $orgName,
            'logoSrc'          => $logoSrc,
            'docTitle'         => 'REPORTING BATCHES',
            'eventTitle'       => $event->title,
            'item'             => $itemModel,
            'participantCount' => $participantCount > 0 ? $participantCount : null,
        ]);

        return PdfGenerator::download(
            $html,
            "{$slug}-reporting-batches.pdf",
            $inline,
            false,
            $headerTemplate,
            $footerTemplate,
            ['top' => '38mm', 'right' => '10mm', 'bottom' => '14mm', 'left' => '10mm'],
        );
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id'    => ['required', 'integer', Rule::exists('fest_event_items', 'id')->where('event_id', $event->id)],
            'label'      => 'required|string|max:255',
            'report_at'  => 'nullable|date',
            'sort_order' => 'nullable|integer',
        ]);

        $data['event_id'] = $event->id;
        $data['sort_order'] = $data['sort_order'] ?? ((int) FestItemReportingBatch::where('item_id', $data['item_id'])->max('sort_order') + 1);

        $batch = FestItemReportingBatch::create($data);

        $audit->festEvent($event, FestPageActivity::REPORTING_BATCHES, 'fest.reporting_batch.created', "Created reporting batch {$batch->label}", [
            'batch_id' => $batch->id,
            'item_id'  => $batch->item_id,
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
            'batch_id'         => ['nullable', 'integer', Rule::exists('fest_item_reporting_batches', 'id')->where('event_id', $event->id)->where('item_id', $request->input('item_id'))],
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

        return [
            'id'                 => $item->id,
            'title'              => $item->title,
            'item_code'          => $item->item_code,
            'category'           => $item->category,
            'is_group'           => app(FestNumberingService::class)->isGroupItem($item),
            'registration_count' => $count,
            'batch_count'        => FestItemReportingBatch::where('item_id', $item->id)->count(),
            'assigned_count'     => $assigned,
            'unassigned_count'   => $count - $assigned,
        ];
    }

    private function batchesForItem(FestEvent $event, int $itemId): \Illuminate\Support\Collection
    {
        return FestItemReportingBatch::where('event_id', $event->id)
            ->where('item_id', $itemId)
            ->withCount('registrations')
            ->orderBy('sort_order')
            ->get();
    }
}
