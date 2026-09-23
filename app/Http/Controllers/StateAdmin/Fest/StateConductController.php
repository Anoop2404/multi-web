<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgramItem;
use App\Models\State\StateAttendance;
use App\Models\State\StateConductAudit;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateConductService;
use App\Services\State\Fest\StateResultService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Phases 6 and 7 of the State Kalotsav module — attendance, mark entry, results and standings. */
class StateConductController extends Controller
{
    public function attendance(Request $request, StateFestEvent $event, StateConductService $conduct)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate(['item_id' => 'nullable|uuid', 'sahodaya_id' => 'nullable|uuid']);

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->when($filters['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->when($filters['sahodaya_id'] ?? null, fn ($q, $v) => $q->where('sahodaya_id', $v))
            ->with('participants')
            ->orderBy('item_code')->orderBy('sahodaya_name')->get();

        $marked = StateAttendance::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->pluck('id'))->pluck('status', 'registration_id');

        return Inertia::render('State/Fest/Attendance', $this->shell($request, $event) + [
            'rows' => $registrations->map(fn (StateFestRegistration $r) => [
                'id' => $r->id,
                'item_code' => $r->item_code,
                'chest_number' => $r->participants->first()->chest_number,
                'participants' => $r->participants->filter(fn ($p) => $p->isCompeting())->pluck('student_name')->implode(', '),
                'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                'school' => $r->school_name ?: $r->school_id,
                'status' => $marked[$r->id] ?? null,
            ]),
            'statuses' => StateConductService::ATTENDANCE_STATUSES,
            'items' => FestStateProgramItem::where('state_program_id', $event->state_program_id)
                ->orderBy('title')->get(['id', 'item_code', 'title']),
            'filters' => $filters,
            'corrections' => StateConductAudit::where('state_event_id', $event->id)->where('kind', 'attendance')
                ->orderByDesc('id')->limit(50)->get()
                ->map(fn ($a) => [
                    'item_code' => $a->item_code, 'from' => $a->value_from, 'to' => $a->value_to,
                    'by' => $a->changed_by_name, 'at' => $a->created_at?->toDateTimeString(), 'reason' => $a->reason,
                ]),
            'actionUrls' => ['mark' => "/admin/state/fest/{$event->id}/attendance"],
            'baseUrl' => "/admin/state/fest/{$event->id}/attendance",
        ]);
    }

    public function markAttendance(Request $request, StateFestEvent $event, StateConductService $conduct)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'statuses'   => 'required|array|min:1',
            'statuses.*' => ['required', Rule::in(StateConductService::ATTENDANCE_STATUSES)],
            'reason'     => 'nullable|string|max:255',
        ]);

        $result = $conduct->markAttendanceBulk($event, $data['statuses'], [
            'reason' => $data['reason'] ?? null,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ]);

        return back()->with('success', "{$result['marked']} marked".($result['corrected'] ? ", {$result['corrected']} corrected" : '').'.');
    }

    public function marks(Request $request, StateFestEvent $event, StateConductService $conduct)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate(['item_id' => 'nullable|uuid']);
        $itemId = $filters['item_id'] ?? null;

        $registrations = $itemId
            ? StateFestRegistration::where('state_event_id', $event->id)->where('item_id', $itemId)
                ->where('status', 'approved')->with('participants')->get()
            : collect();

        $marks = StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->pluck('id'))->get()->keyBy('participant_id');

        $attendance = StateAttendance::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->pluck('id'))->pluck('status', 'registration_id');

        return Inertia::render('State/Fest/Marks', $this->shell($request, $event) + [
            'items' => FestStateProgramItem::where('state_program_id', $event->state_program_id)
                ->orderBy('title')->get(['id', 'item_code', 'title']),
            'filters' => $filters,
            'settings' => $conduct->markSettings($event),
            'rows' => $registrations->flatMap(fn (StateFestRegistration $r) => $r->participants
                ->filter(fn ($p) => $p->isCompeting())
                ->map(fn ($p) => [
                    'participant_id' => $p->id,
                    'registration_id' => $r->id,
                    'name' => $p->student_name,
                    'chest_number' => $p->chest_number,
                    'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                    'school' => $r->school_name ?: $r->school_id,
                    // A competitor who did not compete cannot be scored, so the row says so rather
                    // than accepting a mark that will be refused.
                    'attendance' => $attendance[$r->id] ?? null,
                    'score' => $marks->get($p->id)?->score,
                    'grade' => $marks->get($p->id)?->grade,
                    'position' => $marks->get($p->id)?->position,
                ]))->values(),
            'actionUrls' => [
                'aggregate' => "/admin/state/fest/{$event->id}/marks/aggregate",
            ],
            'baseUrl' => "/admin/state/fest/{$event->id}/marks",
        ]);
    }

    public function aggregate(Request $request, StateFestEvent $event, StateConductService $conduct)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate(['item_id' => 'required|uuid']);
        $result = $conduct->aggregateItem($event, $data['item_id']);

        return back()->with(
            $result['incomplete'] ? 'warning' : 'success',
            "{$result['aggregated']} mark(s) aggregated."
                .($result['incomplete'] ? ' Incomplete panels for: '.implode(', ', $result['incomplete']).'.' : ''),
        );
    }

    public function results(Request $request, StateFestEvent $event, StateResultService $results)
    {
        StateScope::assertOwns($event->state_id);

        return Inertia::render('State/Fest/Results', $this->shell($request, $event) + [
            'items' => $results->itemResults($event),
            'actionUrls' => [
                'compute'   => "/admin/state/fest/{$event->id}/results/compute",
                'publish'   => "/admin/state/fest/{$event->id}/results/publish",
                'unpublish' => "/admin/state/fest/{$event->id}/results/unpublish",
                'lock'      => "/admin/state/fest/{$event->id}/results/lock",
            ],
        ]);
    }

    public function resultAction(Request $request, StateFestEvent $event, string $action, StateResultService $results)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'item_id' => 'required|uuid',
            'reason'  => 'nullable|string|max:500',
        ]);

        $item = FestStateProgramItem::where('state_program_id', $event->state_program_id)->findOrFail($data['item_id']);
        $context = [
            'reason' => $data['reason'] ?? null,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ];

        switch ($action) {
            case 'compute':
                $computed = $results->computeItem($event, $item);
                $message = "{$computed['ranked']} ranked"
                    .($computed['ties'] ? ", {$computed['ties']} tie(s) to resolve" : '').'.';
                break;
            case 'publish':
                $results->publishItem($event, $item, $context);
                $message = 'Result published.';
                break;
            case 'unpublish':
                $results->unpublishItem($event, $item, $context);
                $message = 'Result withdrawn.';
                break;
            case 'lock':
                $results->lockItem($event, $item, $context);
                $message = 'Result locked.';
                break;
            default:
                abort(404);
        }

        return back()->with('success', $message);
    }

    public function leaderboard(Request $request, StateFestEvent $event, StateResultService $results)
    {
        StateScope::assertOwns($event->state_id);

        $includeProvisional = $request->boolean('provisional');
        $standings = $results->sahodayaStandings($event, $includeProvisional);
        $expanded = $request->query('sahodaya_id');

        return Inertia::render('State/Fest/Leaderboard', $this->shell($request, $event) + [
            'standings' => $standings,
            'includeProvisional' => $includeProvisional,
            'expanded' => $expanded,
            // The drill-down: which Schools earned this Sahodaya's points.
            'contribution' => $expanded
                ? $results->schoolContribution($event, $expanded, $includeProvisional)
                : collect(),
            'individual' => $results->individualChampionship($event, $includeProvisional)->take(25),
            'baseUrl' => "/admin/state/fest/{$event->id}/leaderboard",
        ]);
    }

    /** @return array<string, mixed> */
    private function shell(Request $request, StateFestEvent $event): array
    {
        return [
            'event' => [
                'id' => $event->id, 'name' => $event->name, 'status' => $event->status,
                'starts_on' => $event->starts_on?->toDateString(), 'ends_on' => $event->ends_on?->toDateString(),
                'results_published' => (bool) $event->results_published,
                'scoring_locked' => (bool) $event->scoring_locked,
            ],
            'events' => StateScope::apply(StateFestEvent::query())->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'href' => "/admin/state/fest/{$e->id}"]),
            'sahodayas' => StateSahodaya::query()
                ->when(StateScope::shouldScope(), fn ($q) => $q->forState(StateScope::id()))
                ->orderBy('name')->get(['id', 'name', 'district', 'origin']),
            'permissions' => app(StateFestWorkspaceController::class)->permissionsForRequest($request),
        ];
    }
}
