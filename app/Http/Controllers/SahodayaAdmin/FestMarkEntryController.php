<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsItemHeadReportContext;
use App\Models\FestAttendance;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestMarkSaveService;
use App\Services\Events\FestNumberingService;
use App\Services\Events\FestRankPointService;
use App\Services\Events\FestSportsAutoRankService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;

class FestMarkEntryController extends SahodayaAdminController
{
    use BuildsItemHeadReportContext;

    public function index(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $headId = $this->resolveHeadQueryParam($request->query('head_id') ?? $request->query('head'));
        $itemId = $request->integer('item_id') ?: null;
        $itemIds = $this->itemIdsForHeadFilter($event, $headId, $itemId);

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('status', 'approved')
            ->when($itemIds !== null, fn ($q) => $q->whereIn('item_id', $itemIds))
            ->with(['item', 'school', 'participants.student', 'participants.teacher', 'participants.group'])
            ->get();

        $marks = FestMark::where('event_id', $event->id)->get()->keyBy('participant_id');

        $attendance = FestAttendance::where('event_id', $event->id)
            ->get()
            ->mapWithKeys(fn (FestAttendance $row) => [
                "{$row->item_id}-{$row->participant_id}" => ['status' => $row->status],
            ])
            ->all();

        $selectedHeadId = match (true) {
            $headId === 0 => 'other',
            $headId !== null => $headId,
            default => null,
        };

        $childEvents = [];
        if ($event->event_type === 'sports') {
            $seasonId = $event->parent_event_id ?? $event->id;
            $childEvents = FestEvent::where('parent_event_id', $seasonId)
                ->orWhere('id', $seasonId)
                ->ofType('sports')
                ->orderBy('title')
                ->get(['id', 'title', 'parent_event_id'])
                ->all();
        }

        return $this->inertia('Sahodaya/Events/MarkEntry', $this->withEventActivity($event, FestPageActivity::MARKS, [
            'event'          => $event,
            'registrations'  => $registrations,
            'marks'          => $marks,
            'attendance'     => $attendance,
            'selectedHeadId' => $selectedHeadId,
            'selectedItemId' => $itemId,
            'competitionUrl' => "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/competition",
            'rankPoints'     => $event->event_type === 'sports'
                ? app(FestRankPointService::class)->listForEvent($event)
                : [],
            'childEvents'    => $childEvents,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, FestMarkSaveService $markSave, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        EventLifecycleGate::allowMarkEntry($event);

        $data = $request->validate([
            'participant_id'    => 'required|exists:fest_participants,id',
            'item_id'           => 'required|exists:fest_event_items,id',
            'grade'             => 'nullable|in:A,A+,B,C',
            'position'          => 'nullable|integer|min:1|max:255',
            'score'             => 'nullable|numeric|min:0',
            'measurement_value' => 'nullable|string|max:50',
            'measurement_unit'  => 'nullable|string|max:20',
        ]);

        $teamParticipantIds = $this->expandToTeam($event, (int) $data['item_id'], (int) $data['participant_id']);

        $result = null;
        foreach ($teamParticipantIds as $participantId) {
            $result = $markSave->save($event, [...$data, 'participant_id' => $participantId], $request->user()->id);
        }

        $audit->festEvent($event, FestPageActivity::MARKS, 'fest.mark.saved', "Mark saved for participant #{$data['participant_id']}", [
            'participant_id' => $data['participant_id'],
            'item_id'        => $data['item_id'],
            'team_size'      => count($teamParticipantIds),
        ]);

        return back()->with('success', $result['message'] ?? 'Mark saved.');
    }

    /**
     * For a team/group item, the mark applies to the whole squad — saving
     * it writes the same grade/position/score to every member's row so
     * per-participant certificate/results/points logic keeps working
     * unchanged, while the entry screen shows and edits it once per team.
     *
     * @return list<int>
     */
    private function expandToTeam(FestEvent $event, int $itemId, int $participantId): array
    {
        $participant = FestParticipant::with('registration.item')->find($participantId);
        $item = $participant?->registration?->item;

        if (! $participant || ! $item || ! $participant->group_id
            || ! app(FestNumberingService::class)->isGroupItem($item)) {
            return [$participantId];
        }

        return FestParticipant::where('group_id', $participant->group_id)
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('item_id', $itemId))
            ->pluck('id')
            ->all();
    }

    public function autoRankItem(string $tenantId, FestEvent $event, FestEventItem $item, FestSportsAutoRankService $ranker)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);
        abort_unless($event->event_type === 'sports', 422, 'Auto-rank applies to sports events only.');

        $result = $ranker->rankItem($event, $item);

        return back()->with('success', "Auto-ranked {$result['ranked']} athlete(s) for {$result['item_title']}.");
    }
}
