<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestStateNominationBatch;
use App\Models\FestStateProgram;
use App\Services\State\FestStateSlotCollectionService;
use Illuminate\Http\Request;

/**
 * The parent admin's page for getting a confirmed winner list from schools before State
 * registration opens — see FestStateSlotCollectionService for what "opening" and "approving" do.
 * This sits alongside, not instead of, the existing State Winner Registration page: opening
 * collection here fills that same sheet, and once approved the admin registers with State from
 * there exactly as before.
 */
class FestStateSlotCollectionController extends SahodayaAdminController
{
    /**
     * Every hub event this Sahodaya has linked to a State program, one place to reach any of
     * them instead of having to already be on that event's own Levels tab. Includes events from
     * every fest program (Kalotsav, Sports Meet, ...), not just Kalotsav — a State program can be
     * attached to any of them.
     */
    public function overview(string $tenantId)
    {
        $events = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->whereNull('parent_event_id')
            ->whereNotNull('state_program_id')
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'event_start', 'event_end', 'state_program_id',
                'state_slot_collection_open', 'state_slot_collection_opened_at', 'state_slot_collection_approved_at']);

        $programs = FestStateProgram::whereIn('id', $events->pluck('state_program_id')->unique())
            ->get(['id', 'title'])->keyBy('id');

        $batchesByEvent = FestStateNominationBatch::whereIn('hub_event_id', $events->pluck('id'))
            ->get()->keyBy('hub_event_id');

        $rows = $events->map(function (FestEvent $event) use ($programs, $batchesByEvent) {
            $batch = $batchesByEvent->get($event->id);
            $primaries = $batch ? $batch->selections()->where('nomination_type', 'primary')->where('status', 'selected')->get() : collect();

            return [
                'id' => $event->id,
                'title' => $event->title,
                'event_type' => $event->event_type,
                'program_title' => $programs->get($event->state_program_id)?->title,
                'event_start' => $event->event_start,
                'event_end' => $event->event_end,
                'collection_open' => (bool) $event->state_slot_collection_open,
                'collection_opened_at' => $event->state_slot_collection_opened_at,
                'collection_approved_at' => $event->state_slot_collection_approved_at,
                'pending' => $primaries->where('school_response', 'pending')->count(),
                'accepted' => $primaries->where('school_response', 'accepted')->count(),
                'url' => "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/state-slot-collection",
            ];
        });

        return $this->inertia('Sahodaya/Events/StateSlotCollectionOverview', [
            'events' => $rows,
            'sahodaya' => $this->sahodaya,
            'publicUrl' => $this->publicUrl,
        ]);
    }

    public function index(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection)
    {
        $program = $this->context($event);

        return $this->inertia('Sahodaya/Events/StateSlotCollection', [
            'sahodaya' => $this->sahodaya,
            'publicUrl' => $this->publicUrl,
            'event' => $event->only([
                'id', 'title', 'event_type', 'state_program_id',
                'state_slot_collection_open', 'state_slot_collection_opened_at',
                'state_slot_collection_approved_at', 'state_slot_collection_approved_by',
            ]),
            'program' => $program->only(['id', 'title']),
            'summary' => $collection->summary($program, $event),
            'actionUrls' => $this->actionUrls($this->sahodaya->id, $event),
        ]);
    }

    public function open(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection, Request $request)
    {
        $program = $this->context($event);

        $result = $collection->open($program, $event, $request->user());

        return back()->with(
            $result['skipped_ties'] ? 'warning' : 'success',
            "Slot collection opened — {$result['filled']} slot(s) sent to schools for confirmation."
                .($result['skipped_ties']
                    ? ' Left for you to decide first, because a tie has to be broken: '.implode(', ', $result['skipped_ties']).'.'
                    : ''),
        );
    }

    public function approve(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection, Request $request)
    {
        $program = $this->context($event);

        $result = $collection->approve($program, $event, $request->user());

        return back()->with(
            'success',
            "Approved. {$result['approved_count']} winner(s) confirmed — you can now register with State from the winner sheet."
                .($result['warnings'] ? ' '.implode(' ', $result['warnings']) : ''),
        );
    }

    public function updateSlotQuotas(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection, Request $request)
    {
        $program = $this->context($event);
        abort_if($event->state_slot_collection_approved_at, 422, 'Cannot change slots after the list has been approved.');

        $data = $request->validate([
            'default_quota' => 'nullable|integer|min:1|max:10',
            'slots' => 'nullable|array',
            'slots.*' => 'nullable|integer|min:1|max:10',
            'sync_now' => 'nullable|boolean',
        ]);

        $nominations = app(\App\Services\State\FestStateNominationService::class);
        $sourceEvents = $nominations->sourceEvents($event, app(\App\Services\Events\FestPartitionService::class));
        $sourceEventIds = collect($sourceEvents)->pluck('id');

        if (! empty($data['default_quota'])) {
            \App\Models\FestEventItem::whereIn('event_id', $sourceEventIds)
                ->whereNotNull('state_program_item_id')
                ->update(['qualify_count' => (int) $data['default_quota']]);
        }

        if (! empty($data['slots'])) {
            foreach ($data['slots'] as $stateItemId => $quota) {
                if ($quota !== null) {
                    \App\Models\FestEventItem::whereIn('event_id', $sourceEventIds)
                        ->where('state_program_item_id', $stateItemId)
                        ->update(['qualify_count' => (int) $quota]);
                }
            }
        }

        $syncMsg = '';
        if ($event->state_slot_collection_open && ! empty($data['sync_now'])) {
            $result = $collection->syncAdditionalSlots($program, $event, $request->user());
            if ($result['filled'] > 0) {
                $syncMsg = " and {$result['filled']} newly added slot(s) sent to schools for confirmation";
            }
        }

        return back()->with('success', "Slot quotas updated successfully{$syncMsg}.");
    }

    private function context(FestEvent $event): FestStateProgram
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $event->state_program_id, 422, 'This event is not linked to a State program.');
        abort_if($event->parent_event_id, 422, 'Only the hub event collects State slot responses.');

        return FestStateProgram::findOrFail($event->state_program_id);
    }

    /** @return array<string, string> */
    private function actionUrls(string $tenantId, FestEvent $event): array
    {
        $base = "/sahodaya-admin/{$tenantId}/events/{$event->id}/state-slot-collection";

        return [
            'open' => "{$base}/open",
            'approve' => "{$base}/approve",
            'updateSlots' => "{$base}/update-slots",
            'overview' => "/sahodaya-admin/{$tenantId}/state-slot-collection",
        ];
    }
}
