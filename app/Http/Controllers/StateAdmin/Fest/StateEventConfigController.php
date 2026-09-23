<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateEventStaff;
use App\Models\State\StateFestEvent;
use App\Models\State\StateVenue;
use App\Services\State\Fest\StateEventSettings;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Phase 3 of the State Kalotsav module — event configuration: settings and windows, the item
 * catalog, venues and stages, and event staff.
 */
class StateEventConfigController extends Controller
{
    public function settings(Request $request, StateFestEvent $event, StateEventSettings $settings)
    {
        StateScope::assertOwns($event->state_id);

        return Inertia::render('State/Fest/Settings', $this->shell($request, $event) + [
            'settings' => $settings->forDisplay($event),
            'actionUrls' => [
                'save' => "/admin/state/fest/{$event->id}/settings",
            ],
        ]);
    }

    /**
     * What the public can currently see, and the switches that decide it.
     *
     * Separate from Settings on purpose: releasing results is the one action on this module that
     * cannot be taken back quietly, and it should not be one checkbox among thirty on a form whose
     * Save button also changes contact phone numbers.
     */
    public function publicPortal(Request $request, StateFestEvent $event, \App\Services\State\Fest\StatePublicPortalService $portal)
    {
        StateScope::assertOwns($event->state_id);

        $visibility = $portal->visibility($event);

        return Inertia::render('State/Fest/PublicPortal', $this->shell($request, $event) + [
            'visibility' => $visibility,
            'counts' => [
                'scheduled_items' => \App\Models\State\StateItemSchedule::where('state_event_id', $event->id)->count(),
                'published_items' => \App\Models\State\StateItemResult::where('state_event_id', $event->id)
                    ->whereIn('status', [\App\Models\State\StateItemResult::PUBLISHED, \App\Models\State\StateItemResult::LOCKED])->count(),
                'public_results' => $visibility['results'] ? $portal->results($event)->count() : 0,
                'ranked_sahodayas' => $visibility['ranking'] ? $portal->ranking($event)->count() : 0,
            ],
            'links' => [
                'home' => url('/state/kalotsav'),
                'schedule' => url("/state/kalotsav/{$event->id}/schedule"),
                'results' => url("/state/kalotsav/{$event->id}/results"),
                'ranking' => url("/state/kalotsav/{$event->id}/ranking"),
                'verify' => url('/state/certificates/verify'),
            ],
            'actionUrls' => [
                'save' => "/admin/state/fest/{$event->id}/public-portal",
                'settings' => "/admin/state/fest/{$event->id}/settings",
            ],
        ]);
    }

    /**
     * Change only what the public sees. Gated by the publish capability rather than settings, so
     * releasing results is a distinct trust from configuring the event — and nothing else on the
     * settings form can move through this endpoint.
     */
    public function savePublicVisibility(Request $request, StateFestEvent $event, StateEventSettings $settings)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'public_schedule_visible' => 'required|boolean',
            'public_results_visible'  => 'required|boolean',
            'public_ranking_visible'  => 'required|boolean',
        ]);

        $settings->update($event, $data);

        return back()->with('success', 'Public visibility updated.');
    }

    public function saveSettings(Request $request, StateFestEvent $event, StateEventSettings $settings)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'name'      => 'nullable|string|max:255',
            'status'    => ['nullable', Rule::in(['draft', 'active', 'completed', 'archived'])],
            'starts_on' => 'nullable|date',
            'ends_on'   => 'nullable|date|after_or_equal:starts_on',

            'contact_name'  => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:40',
            'contact_email' => 'nullable|email|max:255',
            'venue_summary' => 'nullable|string|max:500',

            'qualifier_opens_at'  => 'nullable|date',
            'qualifier_closes_at' => 'nullable|date|after_or_equal:qualifier_opens_at',
            'scrutiny_opens_at'   => 'nullable|date',
            'scrutiny_closes_at'  => 'nullable|date|after_or_equal:scrutiny_opens_at',

            'public_schedule_visible' => 'boolean',
            'public_results_visible'  => 'boolean',
            'public_ranking_visible'  => 'boolean',
            'registrations_locked'    => 'boolean',
            'scoring_locked'          => 'boolean',
            'notify_on_submission'    => 'boolean',
            'notify_on_approval'      => 'boolean',
        ]);

        // The event's own columns, which are queried rather than merely read.
        $event->forceFill(array_filter([
            'name'      => $data['name'] ?? null,
            'status'    => $data['status'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on'   => $data['ends_on'] ?? null,
        ], fn ($v) => $v !== null) + [
            'scoring_locked' => (bool) ($data['scoring_locked'] ?? $event->scoring_locked),
        ])->save();

        $settings->update($event, $data);

        return back()->with('success', 'Event settings saved.');
    }

    public function items(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);

        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->orderBy('display_order')->orderBy('title')->get();

        return Inertia::render('State/Fest/Items', $this->shell($request, $event) + [
            'items' => $items->map(fn (FestStateProgramItem $i) => [
                'id' => $i->id,
                'item_code' => $i->item_code,
                'title' => $i->title,
                'class_group' => $i->class_group,
                'gender' => $i->gender,
                'participant_type' => $i->participant_type,
                'stage_type' => $i->stage_type,
                'duration_minutes' => $i->duration_minutes,
                'min_group_size' => $i->min_group_size,
                'max_group_size' => $i->max_group_size,
                // The effective per-Sahodaya allowance, so the catalog and the Slots tab agree.
                'slots' => $i->max_per_school ?: ($i->qualify_count ?: null),
                'fee_amount' => $i->fee_amount,
            ]),
            'slotsUrl' => "/admin/state/fest/{$event->id}/slots",
        ]);
    }

    public function venues(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);

        $venues = StateVenue::where('state_event_id', $event->id)
            ->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('State/Fest/Venues', $this->shell($request, $event) + [
            'venues' => $venues->map(fn (StateVenue $v) => [
                'id' => $v->id, 'name' => $v->name, 'code' => $v->code, 'kind' => $v->kind,
                'parent_id' => $v->parent_id, 'capacity' => $v->capacity, 'address' => $v->address,
                'directions' => $v->directions, 'officer_name' => $v->officer_name,
                'officer_phone' => $v->officer_phone, 'is_active' => $v->is_active,
                'can_host_items' => $v->canHostItems(),
            ]),
            'kinds' => StateVenue::KINDS,
            'actionUrls' => [
                'store'   => "/admin/state/fest/{$event->id}/venues",
                'destroy' => "/admin/state/fest/{$event->id}/venues",
            ],
        ]);
    }

    public function storeVenue(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'id'            => 'nullable|uuid',
            'name'          => 'required|string|max:255',
            'code'          => 'nullable|string|max:40',
            'kind'          => ['required', Rule::in(StateVenue::KINDS)],
            'parent_id'     => 'nullable|uuid',
            'capacity'      => 'nullable|integer|min:1|max:100000',
            'address'       => 'nullable|string|max:255',
            'directions'    => 'nullable|string|max:2000',
            'officer_name'  => 'nullable|string|max:255',
            'officer_phone' => 'nullable|string|max:40',
            'is_active'     => 'boolean',
        ]);

        // A place cannot sit inside a place from another event, and cannot be its own parent.
        if (! empty($data['parent_id'])) {
            abort_unless(
                StateVenue::where('state_event_id', $event->id)->where('id', $data['parent_id'])->exists(),
                422,
                'That parent venue belongs to another event.',
            );
            abort_if(($data['id'] ?? null) === $data['parent_id'], 422, 'A venue cannot contain itself.');
        }

        StateVenue::updateOrCreate(
            ['id' => $data['id'] ?? (string) \Str::uuid()],
            array_merge($data, [
                'state_event_id' => $event->id,
                'state_id'       => $event->state_id,
            ]),
        );

        return back()->with('success', 'Venue saved.');
    }

    public function destroyVenue(Request $request, StateFestEvent $event, StateVenue $venue)
    {
        StateScope::assertOwns($event->state_id);
        abort_unless($venue->state_event_id === $event->id, 404);

        // Deleting a venue that still contains stages would orphan them into an event with no
        // parent, which reads as data loss on the schedule.
        abort_if(
            StateVenue::where('parent_id', $venue->id)->exists(),
            422,
            'Remove the stages and rooms inside this venue first.',
        );

        abort_if(
            StateEventStaff::where('venue_id', $venue->id)->exists(),
            422,
            'Staff are still assigned to this venue.',
        );

        $venue->delete();

        return back()->with('success', 'Venue removed.');
    }

    public function staff(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);

        $staff = StateEventStaff::where('state_event_id', $event->id)->orderBy('role')->orderBy('name')->get();

        return Inertia::render('State/Fest/Staff', $this->shell($request, $event) + [
            'staff' => $staff->map(fn (StateEventStaff $s) => [
                'id' => $s->id, 'name' => $s->name, 'phone' => $s->phone, 'email' => $s->email,
                'role' => $s->role, 'role_label' => $s->roleLabel(), 'venue_id' => $s->venue_id,
                'notes' => $s->notes, 'is_active' => $s->is_active,
            ]),
            'roles' => StateEventStaff::ROLES,
            'venues' => StateVenue::where('state_event_id', $event->id)->orderBy('name')->get(['id', 'name', 'kind']),
            'actionUrls' => [
                'store'   => "/admin/state/fest/{$event->id}/staff",
                'destroy' => "/admin/state/fest/{$event->id}/staff",
            ],
        ]);
    }

    public function storeStaff(Request $request, StateFestEvent $event)
    {
        StateScope::assertOwns($event->state_id);

        $data = $request->validate([
            'id'        => 'nullable|uuid',
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:40',
            'email'     => 'nullable|email|max:255',
            'role'      => ['required', Rule::in(array_keys(StateEventStaff::ROLES))],
            'venue_id'  => 'nullable|uuid',
            'notes'     => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if (! empty($data['venue_id'])) {
            abort_unless(
                StateVenue::where('state_event_id', $event->id)->where('id', $data['venue_id'])->exists(),
                422,
                'That venue belongs to another event.',
            );
        }

        StateEventStaff::updateOrCreate(
            ['id' => $data['id'] ?? (string) \Str::uuid()],
            array_merge($data, ['state_event_id' => $event->id, 'state_id' => $event->state_id]),
        );

        return back()->with('success', 'Staff member saved.');
    }

    public function destroyStaff(Request $request, StateFestEvent $event, StateEventStaff $staff)
    {
        StateScope::assertOwns($event->state_id);
        abort_unless($staff->state_event_id === $event->id, 404);

        $staff->delete();

        return back()->with('success', 'Staff member removed.');
    }

    /** @return array<string, mixed> */
    private function shell(Request $request, StateFestEvent $event): array
    {
        $program = FestStateProgram::find($event->state_program_id);

        return [
            'event' => [
                'id' => $event->id, 'name' => $event->name, 'status' => $event->status,
                'starts_on' => $event->starts_on?->toDateString(), 'ends_on' => $event->ends_on?->toDateString(),
                'results_published' => (bool) $event->results_published,
                'scoring_locked' => (bool) $event->scoring_locked,
                'program_title' => $program?->title,
            ],
            'events' => StateScope::apply(StateFestEvent::query())->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'href' => "/admin/state/fest/{$e->id}"]),
            'sahodayas' => [],
            'permissions' => app(StateFestWorkspaceController::class)->permissionsForRequest($request),
        ];
    }
}
