<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestJudgeAssignment;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestJudgeAssignmentController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $assignments = FestJudgeAssignment::where('event_id', $event->id)
            ->with(['item', 'user'])
            ->get();

        $judges = User::role(['judge', 'mark_entry_admin', 'mark_entry_coordinator'])
            ->where('tenant_id', $this->sahodaya->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return $this->inertia('Sahodaya/Events/Judges', $this->withEventActivity($event, FestPageActivity::JUDGES, [
            'event'       => $event,
            'assignments' => $assignments,
            'judges'      => $judges,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id' => 'required|exists:fest_event_items,id',
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('tenant_id', $this->sahodaya->id),
            ],
        ]);

        FestJudgeAssignment::firstOrCreate([
            'event_id' => $event->id,
            'item_id'  => $data['item_id'],
            'user_id'  => $data['user_id'],
        ]);

        $audit->festEvent($event, FestPageActivity::JUDGES, 'fest.judge.assigned', 'Judge assigned to item', [
            'item_id' => $data['item_id'],
            'user_id' => $data['user_id'],
        ]);

        return back()->with('success', 'Judge assigned.');
    }

    public function destroy(string $tenantId, FestEvent $event, FestJudgeAssignment $assignment, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($assignment->event_id !== $event->id, 403);
        $assignment->delete();

        $audit->festEvent($event, FestPageActivity::JUDGES, 'fest.judge.unassigned', 'Judge assignment removed', [
            'assignment_id' => $assignment->id,
        ]);

        return back()->with('success', 'Assignment removed.');
    }
}
