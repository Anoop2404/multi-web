<?php

namespace App\Http\Controllers\StateAdmin\Fest;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgram;
use App\Models\State\StateFestEvent;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StateFestOverviewService;
use App\Support\StateFestPermissions;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Phase 2 of the State Kalotsav module — the event workspace shell.
 *
 * Separate from StateAdmin\StateFestWorkspaceController (the thin pre-module workspace), which stays
 * where it is until Phase 11 switches the routes over. Nothing here reuses a Sahodaya controller.
 */
class StateFestWorkspaceController extends Controller
{
    public function overview(Request $request, StateFestEvent $event, StateFestOverviewService $overview)
    {
        $this->assertOwns($event);

        return Inertia::render('State/Fest/Overview', $this->shell($request, $event) + [
            'metrics' => $overview->forEvent($event),
        ]);
    }

    /**
     * Everything the workspace chrome needs on every tab: which event, which others exist to switch
     * to, the Sahodaya list the shared filter bar is built from, and the permissions the sidebar
     * filters itself with.
     *
     * @return array<string, mixed>
     */
    private function shell(Request $request, StateFestEvent $event): array
    {
        $program = FestStateProgram::find($event->state_program_id);

        return [
            'event' => [
                'id'                => $event->id,
                'name'              => $event->name,
                'status'            => $event->status,
                'starts_on'         => $event->starts_on?->toDateString(),
                'ends_on'           => $event->ends_on?->toDateString(),
                'results_published' => (bool) $event->results_published,
                'scoring_locked'    => (bool) $event->scoring_locked,
                'program_title'     => $program?->title,
            ],
            // The event switcher — scoped, so a state user never sees another state's events.
            'events' => StateScope::apply(StateFestEvent::query())
                ->orderByDesc('starts_on')
                ->get(['id', 'name', 'status'])
                ->map(fn (StateFestEvent $e) => [
                    'id'   => $e->id,
                    'name' => $e->name,
                    'status' => $e->status,
                    'href' => "/admin/state/fest/{$e->id}",
                ]),
            // Sahodaya is the primary filter everywhere in this module; School is secondary and is
            // loaded per Sahodaya by the screens that need it, not eagerly for all of them.
            'sahodayas' => $this->directoryFor($event)
                ->orderBy('name')
                ->get(['id', 'name', 'district', 'origin', 'tenant_id'])
                ->map(fn (StateSahodaya $s) => [
                    'id'       => $s->id,
                    'name'     => $s->name,
                    'district' => $s->district,
                    'origin'   => $s->origin,
                    'on_platform' => $s->isOnPlatform(),
                ]),
            'permissions' => $this->permissionsFor($request),
        ];
    }

    /**
     * The Sahodaya directory this user may filter by.
     *
     * A state user is scoped to their own state and fails closed, as everywhere else. A superadmin
     * is not scoped — which also matters in practice, because programs and tenants created before
     * multi-state carry a null state_id, and scoping a superadmin to the event's state would show
     * them an empty dropdown for exactly the events that predate it.
     */
    private function directoryFor(StateFestEvent $event)
    {
        if (StateScope::shouldScope()) {
            return StateSahodaya::query()->forState(StateScope::id());
        }

        $stateId = $event->state_id;

        return $stateId
            ? StateSahodaya::query()->forState($stateId)
            : StateSahodaya::query();
    }

    /**
     * The State permissions the signed-in user holds, so the sidebar can hide what their role
     * cannot open. Superadmin holds the lot.
     *
     * @return list<string>
     */
    private function permissionsFor(Request $request): array
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return StateFestPermissions::all();
        }

        return array_values(array_filter(
            StateFestPermissions::all(),
            fn (string $permission) => (bool) $user?->can($permission),
        ));
    }

    private function assertOwns(StateFestEvent $event): void
    {
        StateScope::assertOwns($event->state_id);
    }
}
