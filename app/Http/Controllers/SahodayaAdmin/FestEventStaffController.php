<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\FestItemHead;
use App\Models\FestStage;
use App\Models\FestVenue;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\TenantUserCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class FestEventStaffController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $assignments = FestEventStaff::where('event_id', $event->id)
            ->with(['stage:id,name', 'venue:id,name', 'head:id,name', 'region:id,name', 'sourcePhase:id,name'])
            ->get();

        $userIds = $assignments->pluck('user_id')->unique();
        $usersById = User::whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id');

        $poolRoles = array_diff(
            TenantUserCatalog::sahodayaAssignableRoles(),
            TenantUserCatalog::sahodayaPortalOnlyRoles(),
        );

        $staffPool = User::role($poolRoles)
            ->where('tenant_id', $this->sahodaya->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $regionOptions = \App\Models\Region::forTenant($this->sahodaya->id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->inertia('Sahodaya/Events/EventStaff', $this->withEventActivity($event, FestPageActivity::EVENT_STAFF, [
            'event'       => $event->only('id', 'title', 'status', 'event_type', 'workflow_mode'),
            'assignments' => $assignments->map(fn (FestEventStaff $a) => [
                'id'       => $a->id,
                'duty'     => $a->duty,
                'user_id'  => $a->user_id,
                'stage_id' => $a->stage_id,
                'venue_id' => $a->venue_id,
                'head_id'  => $a->head_id,
                'region_id'=> $a->region_id,
                'source_phase_id' => $a->source_phase_id,
                'user'     => $usersById->get($a->user_id),
                'stage'    => $a->stage?->only('id', 'name'),
                'venue'    => $a->venue?->only('id', 'name'),
                'head'     => $a->head?->only('id', 'name'),
                'region'   => $a->region?->only('id', 'name'),
                'source_phase' => $a->sourcePhase?->only('id', 'name'),
            ]),
            'staffPool'   => $staffPool,
            'regionOptions' => $regionOptions,
            'phaseOptions' => $event->usesPhasedRegionalBilling()
                ? $event->rootEvent()->phases()
                    ->where(fn ($query) => $query->where('is_regional', true)->orWhereNotNull('region_partition_group'))
                    ->get(['id', 'name', 'is_regional'])
                : collect(),
            'heads'       => Schema::hasTable('fest_item_heads')
                ? FestItemHead::forTenant($this->sahodaya->id)
                    ->forEvent($event->id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
            'stages'      => FestStage::where('event_id', $event->id)
                ->with('venue:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'venue_id']),
            'venues'      => FestVenue::where('event_id', $event->id)->orderBy('name')->get(['id', 'name']),
            'duties'      => $this->festDutiesForEvent($event),
        ]));
    }

    /** @return \Illuminate\Support\Collection<int, array{value: string, label: string}> */
    private function festDutiesForEvent(FestEvent $event)
    {
        $dutyKeys = $event->event_type === 'sports'
            ? TenantUserCatalog::sportsFestEventDuties()
            : TenantUserCatalog::festEventDuties();

        $labels = $event->event_type === 'sports'
            ? TenantUserCatalog::sportsDutyLabels()
            : TenantUserCatalog::dutyLabels();

        return collect($dutyKeys)->map(fn ($d) => [
            'value' => $d,
            'label' => $labels[$d] ?? $d,
        ])->values();
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $regionIds = \App\Models\Region::forTenant($this->sahodaya->id)->pluck('id')->all();

        $data = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('tenant_id', $this->sahodaya->id),
            ],
            'duty' => ['required', Rule::in(TenantUserCatalog::festEventDuties())],
            'stage_id' => [
                'nullable',
                Rule::exists('fest_stages', 'id')->where('event_id', $event->id),
            ],
            'venue_id' => [
                'nullable',
                Rule::exists('fest_venues', 'id')->where('event_id', $event->id),
            ],
            'head_id' => [
                'nullable',
                Rule::when(
                    Schema::hasTable('fest_item_heads'),
                    Rule::exists('fest_item_heads', 'id'),
                ),
            ],
            'region_id' => [
                'nullable',
                Rule::in($regionIds),
            ],
            'source_phase_id' => [
                'nullable',
                Rule::exists('fest_event_phases', 'id')->where('event_id', $event->rootEvent()->id),
            ],
        ]);

        if ($data['duty'] !== 'stage') {
            $data['stage_id'] = null;
        }

        if ($data['duty'] !== 'region_admin') {
            $data['source_phase_id'] = null;
        } elseif (! empty($data['source_phase_id'])) {
            $phase = $event->rootEvent()->phases()->findOrFail($data['source_phase_id']);
            if (! $phase->isRegional()) {
                return back()->withErrors(['source_phase_id' => 'Only a regional phase can be assigned with region scope.']);
            }
            if (! empty($data['region_id']) && ! $phase->allowedRegions()
                ->where('region_id', $data['region_id'])
                ->where('enabled', true)
                ->exists()) {
                return back()->withErrors(['region_id' => 'That region is not enabled for the selected phase.']);
            }
        }

        $headScopedDuty = $data['duty'] === 'discipline'
            || ($event->event_type === 'sports' && $data['duty'] === 'marks');

        if (! $headScopedDuty) {
            $data['head_id'] = null;
        }

        if ($event->event_type === 'sports' && $data['duty'] === 'marks' && empty($data['head_id'])) {
            return back()->withErrors(['head_id' => 'Select an item head for this coordinator.']);
        }

        if (! empty($data['stage_id'])) {
            $stage = FestStage::where('event_id', $event->id)->findOrFail($data['stage_id']);
            $data['venue_id'] = $data['venue_id'] ?? $stage->venue_id;
        }

        $match = [
            'event_id' => $event->id,
            'user_id'  => $data['user_id'],
            'duty'     => $data['duty'],
        ];

        if ($data['duty'] === 'stage') {
            $match['stage_id'] = $data['stage_id'] ?? null;
        } elseif ($headScopedDuty) {
            $match['head_id'] = $data['head_id'] ?? null;
        } elseif ($data['duty'] === 'region_admin') {
            $match['region_id'] = $data['region_id'] ?? null;
            $match['source_phase_id'] = $data['source_phase_id'] ?? null;
        }

        FestEventStaff::firstOrCreate($match, [
            'stage_id' => $data['stage_id'] ?? null,
            'venue_id' => $data['venue_id'] ?? null,
            'head_id'  => $data['head_id'] ?? null,
            'region_id'=> $data['region_id'] ?? null,
            'source_phase_id' => $data['source_phase_id'] ?? null,
        ]);

        $user = User::find($data['user_id']);
        if ($user) {
            if ($data['duty'] === 'marks' && ! $user->hasRole('mark_entry_coordinator')) {
                $user->assignRole('mark_entry_coordinator');
            } elseif ($data['duty'] === 'region_admin') {
                // Region coordinators must NOT receive the unscoped 'fest_ops' role — that grants
                // full access to every event in the Sahodaya, defeating the point of "region" scoping.
                // Access for this duty is enforced separately via EnsureSahodayaAdmin's region_admin
                // branch, keyed off FestEventStaff.region_id for this exact (event, region) pair.
                if (! $user->hasRole('region_admin')) {
                    $user->assignRole('region_admin');
                }
                // Grant the write permissions this duty needs immediately, rather than waiting on
                // the periodic `permissions:sync-staff` command — mark entry, ID cards, registrations,
                // finance, food billing (see TenantUserCatalog::defaultPermissionsForRole()).
                $user->givePermissionTo(TenantUserCatalog::defaultPermissionsForRole('region_admin'));
            } elseif ($data['duty'] !== 'marks' && ! $user->hasRole('fest_ops')) {
                $user->assignRole('fest_ops');
            }
        }

        $audit->festEvent($event, FestPageActivity::EVENT_STAFF, 'fest.event_staff.assigned', 'Event staff assigned', [
            'user_id' => $data['user_id'],
            'duty'    => $data['duty'],
        ]);

        return back()->with('success', 'Event staff assigned.');
    }

    public function destroy(string $tenantId, FestEvent $event, FestEventStaff $assignment, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($assignment->event_id !== $event->id, 404);
        $assignment->delete();

        $audit->festEvent($event, FestPageActivity::EVENT_STAFF, 'fest.event_staff.unassigned', 'Event staff assignment removed', [
            'assignment_id' => $assignment->id,
        ]);

        return back()->with('success', 'Assignment removed.');
    }
}
