<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestStateNominationSelection;
use App\Models\FestStateProgram;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\State\FestStateNominationService;
use Illuminate\Http\Request;

/**
 * WP-04 (docs/STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md §27) — the manual maker/checker
 * nomination workspace. A Sahodaya committee reviews the certified-result candidate pool for
 * a hub event, picks primary + reserve nominees per item (allowing a documented skip of a
 * higher scorer), and a second, different user certifies the batch. Once certified,
 * FestStateQualifierPayloadBuilder prefers this batch over reading marks directly.
 */
class FestStateNominationController extends SahodayaAdminController
{
    public function index(Request $request, string $tenantId, FestEvent $event, FestStateNominationService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $event->state_program_id, 422, 'Event is not linked to a state program.');

        $program = FestStateProgram::findOrFail($event->state_program_id);
        $batch = $service->openBatch($program, $event);

        $candidates = $service->candidatePool($program, $event);
        $selectedMarkIds = $batch->selections()
            ->where('status', 'selected')
            ->pluck('mark_id')
            ->filter()
            ->all();

        $candidates = array_values(array_filter(
            $candidates,
            fn (array $c) => ! in_array($c['mark_id'] ?? null, $selectedMarkIds, true)
        ));

        return $this->inertia('Sahodaya/Events/StateNomination', [
            'event' => $event,
            'batch' => $batch->only(['id', 'status', 'maker_id', 'checker_id', 'certified_at', 'certification_notes']),
            'candidates' => $candidates,
            'selections' => $batch->selections()
                ->where('status', 'selected')
                ->orderBy('item_code')
                ->orderBy('nomination_type')
                ->orderBy('priority_order')
                ->get(),
            'currentUserId' => $request->user()?->id,
        ]);
    }

    public function select(Request $request, string $tenantId, FestEvent $event, FestStateNominationService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $event->state_program_id, 422, 'Event is not linked to a state program.');

        $data = $request->validate([
            'mark_id' => 'nullable|integer',
            'item_id' => 'nullable|uuid',
            'item_code' => 'nullable|string|max:64',
            'item_title' => 'nullable|string|max:255',
            'source_event_id' => 'nullable|integer',
            'registration_id' => 'nullable|integer',
            'participant_id' => 'nullable|integer',
            'partition_key' => 'nullable|string|max:64',
            'school_id' => 'nullable|string|max:64',
            'school_name' => 'nullable|string|max:255',
            'student_name' => 'nullable|string|max:255',
            'class_name' => 'nullable|string|max:64',
            'source_position' => 'nullable|integer',
            'grade' => 'nullable|string|max:8',
            'score' => 'nullable|numeric',
            'nomination_type' => 'required|in:primary,reserve',
            'priority_order' => 'nullable|integer|min:1',
            'skip_reason' => 'nullable|string|max:2000',
        ]);

        $program = FestStateProgram::findOrFail($event->state_program_id);
        $batch = $service->openBatch($program, $event);

        if (! $batch->maker_id) {
            $batch->update(['maker_id' => $request->user()?->id]);
        }

        $selection = $service->select(
            $batch,
            $data,
            $data['nomination_type'],
            $data['priority_order'] ?? 1,
            $request->user(),
            $data['skip_reason'] ?? null,
        );

        $audit->festEvent($event, 'state-nomination', 'fest.state_nomination.selected', "Selected {$selection->student_name} for {$selection->item_title} ({$selection->nomination_type})", [
            'batch_id' => $batch->id,
            'selection_id' => $selection->id,
        ]);

        return back()->with('success', 'Nominee selected.');
    }

    public function unselect(Request $request, string $tenantId, FestEvent $event, FestStateNominationSelection $selection, FestStateNominationService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $selection->batch || $selection->batch->hub_event_id !== $event->id, 403);

        $service->unselect($selection);

        $audit->festEvent($event, 'state-nomination', 'fest.state_nomination.unselected', "Withdrew {$selection->student_name} from {$selection->item_title}", [
            'selection_id' => $selection->id,
        ]);

        return back()->with('success', 'Selection withdrawn.');
    }

    public function certify(Request $request, string $tenantId, FestEvent $event, FestStateNominationService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $event->state_program_id, 422, 'Event is not linked to a state program.');

        $data = $request->validate([
            'certification_notes' => 'nullable|string|max:2000',
        ]);

        $program = FestStateProgram::findOrFail($event->state_program_id);
        $batch = $service->openBatch($program, $event);

        abort_if($batch->selections()->where('status', 'selected')->where('nomination_type', 'primary')->doesntExist(), 422, 'Select at least one primary nominee before certifying.');

        if (! empty($data['certification_notes'])) {
            $batch->update(['certification_notes' => $data['certification_notes']]);
        }

        try {
            $service->certifyCheckerNomination(
                ['id' => $batch->id, 'maker_id' => $batch->maker_id],
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['certify' => $e->getMessage()]);
        }

        $audit->festEvent($event, 'state-nomination', 'fest.state_nomination.certified', 'Certified State nomination batch', [
            'batch_id' => $batch->id,
        ]);

        return back()->with('success', 'Nomination batch certified. It will be used the next time qualifiers are submitted to State.');
    }
}
