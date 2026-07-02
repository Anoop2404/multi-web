<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\SchoolAdmin\Concerns\ForwardsFestProgramActions;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestQualificationService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestSportsAutoRankService;
use Illuminate\Http\Request;

class SportsMeetController extends SchoolAdminController
{
    use ForwardsFestProgramActions;

    protected function festProgramPrefix(): string
    {
        return 'sports';
    }

    private function assertSchoolSportsEvent(FestEvent $event): void
    {
        abort_unless($event->event_type === 'sports', 404);
        abort_unless($event->level_round === 'school', 404);
        abort_unless($event->conducting_school_id === $this->school->id, 403);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
    }

    private function assertSahodayaSportsEvent(FestEvent $event): void
    {
        abort_unless($event->event_type === 'sports', 404);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($event->level_round === 'school', 404);
    }

    public function myEvent(string $tenantId, FestEvent $event, string $tab = 'overview')
    {
        $this->assertSchoolSportsEvent($event);

        $event->load(['items', 'parentEvent:id,title,status']);

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with(['item', 'participants.student', 'participants.teacher'])
            ->get();

        $marks = FestMark::where('event_id', $event->id)->get()->keyBy('participant_id');

        $resultsByItem = FestMark::where('event_id', $event->id)
            ->whereNotNull('position')
            ->with(['participant.student', 'participant.teacher', 'item'])
            ->orderBy('item_id')
            ->orderBy('position')
            ->get()
            ->groupBy('item_id')
            ->map(fn ($group) => $group->map(fn (FestMark $m) => [
                'position'         => $m->position,
                'name'             => $m->participant?->student?->name ?? $m->participant?->teacher?->name,
                'measurement'      => trim("{$m->measurement_value} {$m->measurement_unit}"),
                'score'            => $m->score,
            ])->values());

        $parentEvents = FestEvent::where('tenant_id', $this->school->parent_id)
            ->where('event_type', 'sports')
            ->whereIn('level_round', ['sahodaya', 'state'])
            ->whereIn('status', ['draft', 'published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'level_round', 'status']);

        return $this->inertia('School/Sports/MyEvent', [
            'tab'           => $tab,
            'event'         => $event,
            'parentEvents'  => $parentEvents,
            'registrations' => $registrations,
            'marks'         => $marks,
            'resultsByItem' => $resultsByItem,
            'stats'         => [
                'items'          => $event->items->count(),
                'registrations'  => $registrations->count(),
                'marks_entered'  => $marks->count(),
                'ranked'         => FestMark::where('event_id', $event->id)->whereNotNull('position')->count(),
            ],
        ]);
    }

    public function sahodayaEvent(Request $request, string $tenantId, FestEvent $event)
    {
        $this->assertSahodayaSportsEvent($event);

        return redirect("/school-admin/{$tenantId}/sports/registration?event={$event->id}");
    }

    public function linkParent(Request $request, string $tenantId, FestEvent $event)
    {
        return app(FestProgramController::class)->linkParent($request, $tenantId, $event);
    }

    public function storeMark(Request $request, string $tenantId, FestEvent $event)
    {
        return app(FestProgramController::class)->storeMark($request, $tenantId, $event);
    }

    public function autoRankItem(string $tenantId, FestEvent $event, FestEventItem $item, FestSportsAutoRankService $ranker)
    {
        $this->assertSchoolSportsEvent($event);
        abort_if($item->event_id !== $event->id, 404);

        $result = $ranker->rankItem($event, $item);

        return back()->with('success', "Auto-ranked {$result['ranked']} athlete(s) for {$result['item_title']}.");
    }

    public function submitWinners(string $tenantId, FestQualificationService $qualification)
    {
        return $this->inertia('School/Sports/SubmitWinners', [
            'school' => $this->school->only('id', 'name'),
            'events' => $qualification->schoolSportsWinnerCandidates($this->school),
        ]);
    }

    public function storeWinners(Request $request, string $tenantId, FestQualificationService $qualification, FestEventNotifier $notifier, PlatformAuditLogger $audit)
    {
        $data = $request->validate([
            'school_event_id' => 'required|exists:fest_events,id',
            'mark_ids'        => 'required|array|min:1',
            'mark_ids.*'      => 'integer|exists:fest_marks,id',
        ]);

        $schoolEvent = FestEvent::findOrFail($data['school_event_id']);
        $result = $qualification->submitSchoolSportsWinners($this->school, $schoolEvent, $data['mark_ids']);

        if ($result['submitted'] > 0 && $schoolEvent->parent_event_id) {
            $target = FestEvent::find($schoolEvent->parent_event_id);
            if ($target) {
                $notifier->sportsWinnersReceived($target, $this->school, $result['submitted']);
                $audit->festEvent($target, 'sports', 'sports.winners.submitted',
                    "{$this->school->name} submitted {$result['submitted']} sports winner(s)", [
                    'school_id' => $this->school->id,
                    'count'     => $result['submitted'],
                ]);
            }
        }

        $message = "{$result['submitted']} winner(s) submitted.";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped (already submitted or invalid).";
        }

        return back()->with('success', $message);
    }
}
