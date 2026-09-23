<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Services\State\Fest\StateJudgePortalService;
use Illuminate\Http\Request;

/**
 * Phase 6 of the State Kalotsav module — the judge portal.
 *
 * Deliberately separate from the older Portal\StateJudgeDashboardController, which stays in place
 * until Phase 11 switches over. The difference is not cosmetic: this one goes through
 * StateConductService, so it honours scoring locks, refuses to score an absent or withdrawn entry,
 * and shows the judge chest numbers only.
 */
class StateFestJudgeController extends Controller
{
    public function __construct(private StateJudgePortalService $portal) {}

    public function index(Request $request)
    {
        return inertia('Portal/StateFestJudge/Dashboard', [
            'assignments' => $this->portal->assignments($request->user()->id),
            'judge' => ['name' => $request->user()->name],
        ]);
    }

    public function sheet(Request $request, StateFestEvent $event, string $item)
    {
        $judgeId = $request->user()->id;
        $assignment = $this->portal->assignmentFor($event, $item, $judgeId);

        abort_unless($assignment, 403, 'You are not on the panel for this item.');

        $definition = FestStateProgramItem::find($item);

        return inertia('Portal/StateFestJudge/Sheet', [
            'event' => [
                'id' => $event->id, 'name' => $event->name,
                'scoring_locked' => (bool) $event->scoring_locked,
            ],
            'item' => [
                'id' => $item,
                'code' => $assignment->item_code ?: $definition?->item_code,
                'title' => $definition?->title,
            ],
            'targets' => $this->portal->targets($event, $item, $judgeId),
            'submittedAt' => $assignment->submitted_at?->toDateTimeString(),
            'marks' => app(\App\Services\State\Fest\StateConductService::class)->markSettings($event),
            'actionUrls' => [
                'score' => "/portal/state-fest-judge/{$event->id}/items/{$item}/score",
                'submit' => "/portal/state-fest-judge/{$event->id}/items/{$item}/submit",
                'back' => '/portal/state-fest-judge',
            ],
        ]);
    }

    public function score(Request $request, StateFestEvent $event, string $item)
    {
        $data = $request->validate([
            'participant_id' => 'required|integer',
            'score' => 'required|numeric',
            'grade' => 'nullable|string|max:4',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->portal->score($event, $item, (int) $data['participant_id'], $request->user()->id, $data + [
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
        ]);

        return back()->with('success', 'Score saved.');
    }

    public function submit(Request $request, StateFestEvent $event, string $item)
    {
        $result = $this->portal->submit($event, $item, $request->user()->id);

        return redirect('/portal/state-fest-judge')
            ->with('success', "Sheet submitted — {$result['submitted']} entries scored.");
    }
}
