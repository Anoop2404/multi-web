<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
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
    public function index(string $tenantId, FestEvent $event, FestStateSlotCollectionService $collection)
    {
        $program = $this->context($event);

        return $this->inertia('Sahodaya/Events/StateSlotCollection', [
            'event' => $event->only([
                'id', 'title', 'state_program_id',
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
        ];
    }
}
