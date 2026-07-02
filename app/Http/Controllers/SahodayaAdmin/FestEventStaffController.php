<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\FestStage;
use App\Models\FestVenue;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\TenantUserCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestEventStaffController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $assignments = FestEventStaff::where('event_id', $event->id)
            ->with(['stage:id,name', 'venue:id,name'])
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

        return $this->inertia('Sahodaya/Events/EventStaff', $this->withEventActivity($event, FestPageActivity::EVENT_STAFF, [
            'event'       => $event->only('id', 'title', 'status'),
            'assignments' => $assignments->map(fn (FestEventStaff $a) => [
                'id'       => $a->id,
                'duty'     => $a->duty,
                'user_id'  => $a->user_id,
                'stage_id' => $a->stage_id,
                'venue_id' => $a->venue_id,
                'user'     => $usersById->get($a->user_id),
                'stage'    => $a->stage?->only('id', 'name'),
                'venue'    => $a->venue?->only('id', 'name'),
            ]),
            'staffPool'   => $staffPool,
            'stages'      => FestStage::where('event_id', $event->id)
                ->with('venue:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'venue_id']),
            'venues'      => FestVenue::where('event_id', $event->id)->orderBy('name')->get(['id', 'name']),
            'duties'      => collect(TenantUserCatalog::festEventDuties())->map(fn ($d) => [
                'value' => $d,
                'label' => TenantUserCatalog::dutyLabels()[$d] ?? $d,
            ])->values(),
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

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
        ]);

        if ($data['duty'] !== 'stage') {
            $data['stage_id'] = null;
        }

        if (! empty($data['stage_id'])) {
            $stage = FestStage::where('event_id', $event->id)->findOrFail($data['stage_id']);
            $data['venue_id'] = $data['venue_id'] ?? $stage->venue_id;
        }

        FestEventStaff::firstOrCreate([
            'event_id' => $event->id,
            'user_id'  => $data['user_id'],
            'duty'     => $data['duty'],
            'stage_id' => $data['stage_id'] ?? null,
        ], [
            'venue_id' => $data['venue_id'] ?? null,
        ]);

        $user = User::find($data['user_id']);
        if ($user) {
            if ($data['duty'] === 'marks' && ! $user->hasRole('mark_entry_coordinator')) {
                $user->assignRole('mark_entry_coordinator');
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
