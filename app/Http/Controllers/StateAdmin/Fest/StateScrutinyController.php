<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgramItem;
use App\Models\State\StateAttendance;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateEventSettings;
use App\Services\State\Fest\StateScrutinyService;
use App\Services\State\Fest\StateSlotService;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Phase 4 of the State Kalotsav module — Sahodaya submissions, scrutiny, registrations.
 *
 * Every list is Sahodaya-first with School as the second level and a displayed value, per the
 * module's fixed filter hierarchy.
 */
class StateScrutinyController extends Controller
{
    /** A. Sahodaya Submissions — one row per submitted package. */
    public function submissions(Request $request, StateFestEvent $event, StateEventSettings $settings)
    {
        StateScope::assertOwns($event->state_id);

        $intakes = StateQualifierIntake::where('state_program_id', $event->state_program_id)
            ->where('status', '!=', 'draft')
            ->orderByDesc('created_at')
            ->get();

        $counts = StateQualifierEntry::whereIn('intake_id', $intakes->pluck('id'))
            ->selectRaw('intake_id, status, count(*) as c')
            ->groupBy('intake_id', 'status')->get()->groupBy('intake_id');

        $directory = StateSahodaya::whereIn('id', $intakes->pluck('sahodaya_id')->filter()->unique())->get()->keyBy('id');

        return Inertia::render('State/Fest/Submissions', $this->shell($request, $event) + [
            'submissions' => $intakes->map(function (StateQualifierIntake $intake) use ($counts, $directory, $event) {
                $byStatus = ($counts->get($intake->id) ?? collect())->pluck('c', 'status');
                $sahodaya = $directory->get($intake->sahodaya_id);

                return [
                    'id' => $intake->id,
                    'sahodaya' => $intake->sahodaya_name ?: ($sahodaya?->name ?? $intake->source_tenant_id),
                    'district' => $sahodaya?->district,
                    // Origin, not current tenancy: "arrived from outside" stays true after promotion.
                    'source' => $sahodaya?->origin === StateSahodaya::ORIGIN_EXTERNAL ? 'external' : 'managed',
                    'status' => $intake->status,
                    'submitted_at' => $intake->created_at?->toDateTimeString(),
                    'reviewed_at' => $intake->reviewed_at?->toDateTimeString(),
                    'schools' => StateQualifierEntry::where('intake_id', $intake->id)->distinct()->count('school_id'),
                    'entries' => (int) $byStatus->sum(),
                    'pending' => (int) ($byStatus['pending'] ?? 0),
                    'approved' => (int) ($byStatus['approved'] ?? 0),
                    'rejected' => (int) ($byStatus['rejected'] ?? 0),
                    'returned' => (int) ($byStatus['returned'] ?? 0),
                    'documents_requested' => (int) ($byStatus['documents_requested'] ?? 0),
                    'review_url' => "/admin/state/fest/{$event->id}/scrutiny/{$intake->id}",
                ];
            }),
            'windows' => [
                'qualifier_open' => $settings->windowIsOpen($event, StateEventSettings::WINDOW_QUALIFIER),
                'scrutiny_open'  => $settings->windowIsOpen($event, StateEventSettings::WINDOW_SCRUTINY),
                'scrutiny_note'  => $settings->windowClosedReason($event, StateEventSettings::WINDOW_SCRUTINY),
            ],
        ]);
    }

    /** B. Scrutiny — one submission, entry by entry, with the quota context for each decision. */
    public function scrutiny(Request $request, StateFestEvent $event, StateQualifierIntake $intake, StateSlotService $slots, StateScrutinyService $scrutiny, StateEventSettings $settings)
    {
        StateScope::assertOwns($event->state_id);
        abort_unless($intake->state_program_id === $event->state_program_id, 404);

        $entries = StateQualifierEntry::where('intake_id', $intake->id)->orderBy('item_code')->orderBy('student_name')->get();
        $items = FestStateProgramItem::whereIn('id', $entries->pluck('item_id')->filter()->unique())->get()->keyBy('id');

        return Inertia::render('State/Fest/Scrutiny', $this->shell($request, $event) + [
            'intake' => [
                'id' => $intake->id,
                'sahodaya' => $intake->sahodaya_name ?: $intake->source_tenant_id,
                'status' => $intake->status,
                'submitted_at' => $intake->created_at?->toDateTimeString(),
                'is_final' => in_array($intake->status, ['approved', 'rejected'], true),
            ],
            'entries' => $entries->map(function (StateQualifierEntry $e) use ($items, $slots, $intake) {
                $item = $items->get($e->item_id);

                return [
                    'id' => $e->id,
                    'student_name' => $e->student_name,
                    'class_name' => $e->class_name,
                    'roll_number' => $e->roll_number,
                    // Both names on every row, per the spec: the Sahodaya competes, the School is
                    // where the participant came from.
                    'school_name' => $e->school_name ?: $e->school_id,
                    'item_code' => $e->item_code,
                    'item_name' => $e->item_name ?: $item?->title,
                    'position' => $e->position,
                    'grade' => $e->grade,
                    'is_reserve' => (bool) $e->is_reserve,
                    'status' => $e->status,
                    'review_note' => $e->review_note,
                    // The allowance shown beside the decision, so a scrutineer is not approving
                    // blind and then being refused by the quota check.
                    'slots' => $item ? $slots->slotsFor($item, $intake->sahodaya_id) : null,
                ];
            }),
            'history' => $scrutiny->history($intake)->map(fn ($h) => [
                'id' => $h->id, 'decision' => $h->decision, 'note' => $h->note,
                'by' => $h->decided_by_name, 'at' => $h->created_at?->toDateTimeString(),
                'entry_id' => $h->entry_id,
            ]),
            'scrutinyOpen' => $settings->windowIsOpen($event, StateEventSettings::WINDOW_SCRUTINY),
            'scrutinyNote' => $settings->windowClosedReason($event, StateEventSettings::WINDOW_SCRUTINY),
            'actionUrls' => [
                'decide'   => "/admin/state/fest/{$event->id}/scrutiny/{$intake->id}/decide",
                'reserve'  => "/admin/state/fest/{$event->id}/scrutiny/{$intake->id}/reserve",
                'finalise' => "/admin/state/fest/{$event->id}/scrutiny/{$intake->id}/finalise",
                'reopen'   => "/admin/state/fest/{$event->id}/scrutiny/{$intake->id}/reopen",
                'back'     => "/admin/state/fest/{$event->id}/submissions",
            ],
        ]);
    }

    public function decide(Request $request, StateFestEvent $event, StateQualifierIntake $intake, StateScrutinyService $scrutiny)
    {
        StateScope::assertOwns($event->state_id);
        abort_unless($intake->state_program_id === $event->state_program_id, 404);

        $data = $request->validate([
            'entry_ids'   => 'required|array|min:1',
            'entry_ids.*' => 'integer',
            'decision'    => ['required', Rule::in(StateScrutinyService::DECISIONS)],
            'note'        => 'nullable|string|max:1000',
        ]);

        $entries = StateQualifierEntry::whereIn('id', $data['entry_ids'])->where('intake_id', $intake->id)->get();
        abort_if($entries->isEmpty(), 404, 'Those entries do not belong to this submission.');

        $context = [
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
            'event' => $event,
        ];

        $done = 0;
        $refused = [];

        foreach ($entries as $entry) {
            try {
                $scrutiny->decide($entry, $data['decision'], $context);
                $done++;
            } catch (\Illuminate\Validation\ValidationException $e) {
                // One entry over its Sahodaya's allowance must not abort the rest of the batch —
                // the scrutineer needs to know which one, not lose the work.
                $refused[$entry->student_name] = collect($e->errors())->flatten()->first();
            }
        }

        return back()->with(
            $refused ? 'warning' : 'success',
            trim("{$done} entr".($done === 1 ? 'y' : 'ies')." marked {$data['decision']}. "
                .($refused ? 'Refused: '.collect($refused)->map(fn ($m, $n) => "{$n} — {$m}")->implode('; ') : '')),
        );
    }

    public function acceptReserve(Request $request, StateFestEvent $event, StateQualifierIntake $intake, StateScrutinyService $scrutiny)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'original_id' => 'required|integer',
            'reserve_id'  => 'required|integer|different:original_id',
            'note'        => 'nullable|string|max:1000',
        ]);

        $original = StateQualifierEntry::where('intake_id', $intake->id)->findOrFail($data['original_id']);
        $reserve = StateQualifierEntry::where('intake_id', $intake->id)->findOrFail($data['reserve_id']);

        $scrutiny->acceptReserve($original, $reserve, [
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
            'event' => $event,
        ]);

        return back()->with('success', "{$reserve->student_name} accepted in place of {$original->student_name}.");
    }

    public function finalise(Request $request, StateFestEvent $event, StateQualifierIntake $intake, StateScrutinyService $scrutiny)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        $scrutiny->finalise($intake, [
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ]);

        return back()->with('success', 'Submission finalised.');
    }

    public function reopen(Request $request, StateFestEvent $event, StateQualifierIntake $intake, StateScrutinyService $scrutiny)
    {
        StateScope::assertOwns($event->state_id);

        $scrutiny->reopen($intake, [
            'note' => $request->input('note'),
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
        ]);

        return back()->with('success', 'Submission reopened for review.');
    }

    /** C. All Registrations — the full filter set from the spec. */
    public function registrations(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);

        $filters = $request->validate([
            'sahodaya_id' => 'nullable|uuid',
            'school_id'   => 'nullable|string|max:191',
            'item_id'     => 'nullable|uuid',
            'type'        => 'nullable|in:individual,team',
            'origin'      => 'nullable|in:managed,external',
            'status'      => 'nullable|string|max:30',
            'search'      => 'nullable|string|max:120',
        ]);

        $query = StateFestRegistration::where('state_event_id', $event->id)
            ->when($filters['sahodaya_id'] ?? null, fn ($q, $v) => $q->where('sahodaya_id', $v))
            ->when($filters['school_id'] ?? null, fn ($q, $v) => $q->where('school_id', $v))
            ->when($filters['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['origin'] ?? null, fn ($q, $v) => $q->whereIn(
                'sahodaya_id', StateSahodaya::where('origin', $v)->pluck('id'),
            ))
            ->with('participants')
            ->orderBy('sahodaya_name')->orderBy('school_name')->orderBy('item_code');

        $registrations = $query->paginate(50)->withQueryString();

        $attendance = StateAttendance::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->pluck('status', 'registration_id');

        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $type = $filters['type'] ?? null;

        $registrations->setCollection(
            $registrations->getCollection()->map(function (StateFestRegistration $r) use ($attendance) {
                $names = $r->participants->pluck('student_name')->filter();

                return [
                    'id' => $r->id,
                    'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                    'school' => $r->school_name ?: $r->school_id,
                    'participants' => $names->implode(', ') ?: '—',
                    'class_name' => $r->participants->first()->class_name ?? null,
                    'item_code' => $r->item_code,
                    'is_team' => $names->count() > 1,
                    'chest_number' => $r->participants->first()->chest_number ?? null,
                    'status' => $r->status,
                    'attendance' => $attendance[$r->id] ?? null,
                ];
            })
            // Participant search and team/individual run on the mapped rows because both depend on
            // the participants relation rather than a column on the registration.
            ->filter(fn ($row) => $search === '' || str_contains(strtolower($row['participants']), $search))
            ->filter(fn ($row) => $type === null || ($type === 'team') === $row['is_team'])
            ->values()
        );

        return Inertia::render('State/Fest/Registrations', $this->shell($request, $event) + [
            'registrations' => $registrations,
            'filters' => $filters,
            'items' => FestStateProgramItem::where('state_program_id', $event->state_program_id)
                ->orderBy('title')->get(['id', 'item_code', 'title']),
            'baseUrl' => "/admin/state/fest/{$event->id}/registrations",
        ]);
    }

    /** D. Pending Approvals — entries past intake but not yet decided, across every submission. */
    public function pending(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);

        $intakes = StateQualifierIntake::where('state_program_id', $event->state_program_id)
            ->where('status', '!=', 'draft')->get()->keyBy('id');

        $entries = StateQualifierEntry::whereIn('intake_id', $intakes->keys())
            ->whereIn('status', array_merge(['pending'], StateScrutinyService::OPEN_DECISIONS))
            ->orderBy('status')->orderBy('item_code')->get();

        return Inertia::render('State/Fest/PendingApprovals', $this->shell($request, $event) + [
            'entries' => $entries->map(function (StateQualifierEntry $e) use ($intakes, $event) {
                $intake = $intakes->get($e->intake_id);

                return [
                    'id' => $e->id,
                    'sahodaya' => $intake?->sahodaya_name ?? '—',
                    'school' => $e->school_name ?: $e->school_id,
                    'item_code' => $e->item_code,
                    'student_name' => $e->student_name,
                    'status' => $e->status,
                    'review_note' => $e->review_note,
                    'review_url' => "/admin/state/fest/{$event->id}/scrutiny/{$e->intake_id}",
                ];
            }),
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
