<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Support\AcademicYear;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipationPolicy;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestItemSyncService;
use App\Services\Events\FestParticipationPolicyService;
use App\Support\Fest\FestEventItemPayload;
use Illuminate\Http\Request;

class FestProgramController extends SchoolAdminController
{
    public function index()
    {
        $sahodayaId = $this->school->parent_id;

        $events = FestEvent::where('tenant_id', $sahodayaId)
            ->where('level_round', 'school')
            ->where('conducting_school_id', $this->school->id)
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start')
            ->get();

        $parentEvents = FestEvent::where('tenant_id', $sahodayaId)
            ->whereIn('level_round', ['sahodaya', 'state'])
            ->whereIn('status', ['draft', 'published', 'registration_open', 'ongoing'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'level_round']);

        return $this->inertia('School/Events/Programs', [
            'events'     => $events,
            'parentEvents' => $parentEvents,
            'eventTypes' => $this->eventTypes(),
            'levelLabels'=> FestEvent::levelLabels(),
        ]);
    }

    public function store(Request $request, FestItemSyncService $syncService)
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'event_type'         => 'required|in:kalolsavam,sports,kids_fest,teacher_fest,custom',
            'parent_event_id'    => 'nullable|exists:fest_events,id',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date',
            'event_start'        => 'nullable|date',
            'event_end'          => 'nullable|date',
            'venue'              => 'nullable|string|max:255',
            'description'        => 'nullable|string',
        ]);

        $sahodayaId = $this->school->parent_id;

        if (! empty($data['parent_event_id'])) {
            $parent = FestEvent::findOrFail($data['parent_event_id']);
            abort_if($parent->tenant_id !== $sahodayaId, 403);
            abort_if(($parent->level_round ?? 'sahodaya') === 'school', 422, 'Parent must be a Sahodaya-level event.');
        } else {
            $data['parent_event_id'] = FestEvent::where('tenant_id', $sahodayaId)
                ->where('event_type', $data['event_type'])
                ->whereIn('level_round', ['sahodaya', 'state'])
                ->whereIn('status', ['draft', 'published', 'registration_open', 'ongoing'])
                ->orderByDesc('event_start')
                ->value('id');
        }

        $event = FestEvent::create(array_merge($data, [
            'tenant_id'             => $sahodayaId,
            'academic_year_id'      => AcademicYear::activeId(),
            'conductor_level'       => 'school',
            'conduct_levels'        => ['school'],
            'level_round'           => 'school',
            'conducting_school_id'  => $this->school->id,
            'status'                => 'draft',
        ]));

        $inherited = $syncService->inheritClusterCatalog($event, $this->school);

        app(FestParticipationPolicyService::class)->applyPresetToEvent($event, 'cksc_school_kalakriti');

        $message = "School event \"{$event->title}\" created.";
        if ($inherited > 0) {
            $message .= " Inherited {$inherited} state/Sahodaya item(s).";
        }

        return redirect("/school-admin/{$this->school->id}/fest-programs/{$event->id}")
            ->with('success', $message);
    }

    public function linkParent(Request $request, string $tenantId, FestEvent $festProgram)
    {
        abort_unless($festProgram->level_round === 'school', 404);
        abort_unless($festProgram->conducting_school_id === $this->school->id, 403);

        $data = $request->validate([
            'parent_event_id' => 'required|exists:fest_events,id',
        ]);

        $parent = FestEvent::findOrFail($data['parent_event_id']);
        abort_if($parent->tenant_id !== $this->school->parent_id, 403);
        abort_if($parent->level_round === 'school', 422, 'Parent must be a Sahodaya-level event.');
        abort_if($parent->event_type !== $festProgram->event_type, 422, 'Parent event must be the same program type.');

        $festProgram->update(['parent_event_id' => $parent->id]);

        return back()->with('success', "Linked to Sahodaya event \"{$parent->title}\".");
    }

    public function show(string $tenantId, FestEvent $festProgram)
    {
        abort_unless($festProgram->level_round === 'school', 404);
        abort_unless($festProgram->conducting_school_id === $this->school->id, 403);
        abort_if($festProgram->tenant_id !== $this->school->parent_id, 403);

        if ($festProgram->event_type === 'sports') {
            return redirect("/school-admin/{$tenantId}/sports/my-event/{$festProgram->id}");
        }

        $festProgram->load('items');

        return $this->inertia('School/Events/ProgramShow', [
            'event'            => $festProgram,
            'parentEvents'     => FestEvent::where('tenant_id', $this->school->parent_id)
                ->where('event_type', $festProgram->event_type)
                ->whereIn('level_round', ['sahodaya', 'state'])
                ->whereIn('status', ['draft', 'published', 'registration_open', 'ongoing', 'completed'])
                ->orderByDesc('event_start')
                ->get(['id', 'title', 'level_round', 'status']),
            'eventTypes'       => $this->eventTypes(),
            'itemsByLevel'     => [
                'state'    => $festProgram->items->where('owner_level', 'state')->values(),
                'sahodaya' => $festProgram->items->where('owner_level', 'sahodaya')->values(),
                'school'   => $festProgram->items->where('owner_level', 'school')->values(),
            ],
            'ownerLevelLabels' => [
                'state'    => 'From state',
                'sahodaya' => 'From Sahodaya',
                'school'   => 'School custom',
            ],
            'taxonomy'         => config('fest_item_taxonomy'),
            'participationPolicy' => FestParticipationPolicy::where('event_id', $festProgram->id)->whereNull('class_group')->first(),
            'participationPresets' => app(FestParticipationPolicyService::class)->presetOptions(),
        ]);
    }

    public function storePolicy(Request $request, string $tenantId, FestEvent $festProgram, FestParticipationPolicyService $service)
    {
        abort_unless($festProgram->conducting_school_id === $this->school->id, 403);

        $data = $request->validate([
            'preset_key' => 'nullable|string|max:60',
            'max_onstage_per_student' => 'nullable|integer|min:0',
            'max_offstage_per_student' => 'nullable|integer|min:0',
            'max_group_per_student' => 'nullable|integer|min:0',
        ]);

        if (! empty($data['preset_key'])) {
            $service->applyPresetToEvent($festProgram, $data['preset_key']);

            return back()->with('success', 'Participation policy preset applied.');
        }

        FestParticipationPolicy::updateOrCreate(
            ['event_id' => $festProgram->id, 'class_group' => null],
            array_merge($data, [
                'tenant_id' => $festProgram->tenant_id,
                'scope' => 'event',
                'level_round' => 'school',
                'is_active' => true,
            ])
        );

        return back()->with('success', 'Participation policy saved.');
    }

    public function storeItem(Request $request, string $tenantId, FestEvent $festProgram)
    {
        abort_unless($festProgram->conducting_school_id === $this->school->id, 403);

        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'class_group'      => 'nullable|in:lp,up,hs,hss,open',
            'gender'           => 'nullable|in:male,female,mixed,open',
            'participant_type' => 'nullable|in:individual,group,team',
        ]);

        $data['event_id'] = $festProgram->id;
        $data['owner_level'] = 'school';
        $data['participant_type'] = $data['participant_type'] ?? 'individual';
        $data = FestEventItemPayload::applyDefaults($data);
        $data['display_order'] = ($festProgram->items()->max('display_order') ?? 0) + 1;

        FestEventItem::create($data);

        return back()->with('success', 'School custom item added.');
    }

    public function destroyItem(string $tenantId, FestEvent $festProgram, FestEventItem $item)
    {
        abort_unless($festProgram->conducting_school_id === $this->school->id, 403);
        abort_if($item->event_id !== $festProgram->id, 404);
        abort_unless($item->isEditableBySchool(), 422, 'Only school custom items can be removed here.');

        $item->delete();

        return back()->with('success', 'Item removed.');
    }

    public function marks(string $tenantId, FestEvent $festProgram)
    {
        abort_unless($festProgram->level_round === 'school', 404);
        abort_unless($festProgram->conducting_school_id === $this->school->id, 403);

        $festProgram->load('items');

        $registrations = \App\Models\FestRegistration::where('event_id', $festProgram->id)
            ->where('status', 'approved')
            ->with(['item', 'participants.student', 'participants.teacher'])
            ->get();

        $marks = \App\Models\FestMark::where('event_id', $festProgram->id)
            ->get()
            ->keyBy('participant_id');

        return $this->inertia('School/Events/ProgramMarks', [
            'school'        => $this->school->only('id', 'name'),
            'event'         => $festProgram->only('id', 'title', 'event_type', 'status'),
            'registrations' => $registrations,
            'marks'         => $marks,
        ]);
    }

    public function storeMark(Request $request, string $tenantId, FestEvent $festProgram, \App\Services\Events\FestMarkSaveService $markSave, PlatformAuditLogger $audit)
    {
        abort_unless($festProgram->conducting_school_id === $this->school->id, 403);

        \App\Services\Events\EventLifecycleGate::allowMarkEntry($festProgram);

        $data = $request->validate([
            'participant_id'    => 'required|exists:fest_participants,id',
            'item_id'           => 'required|exists:fest_event_items,id',
            'grade'             => 'nullable|in:A,A+,B,C',
            'position'          => 'nullable|integer|min:1|max:255',
            'score'             => 'nullable|numeric|min:0',
            'measurement_value' => 'nullable|string|max:50',
            'measurement_unit'  => 'nullable|string|max:20',
        ]);

        $result = $markSave->save($festProgram, $data, $request->user()->id);

        $audit->festEvent($festProgram, 'marks', 'fest.mark.saved', "Mark saved for participant #{$data['participant_id']}", [
            'participant_id' => $data['participant_id'],
            'item_id'        => $data['item_id'],
        ]);

        return back()->with('success', $result['message']);
    }

    private function eventTypes(): array
    {
        return [
            'kalolsavam'   => 'Kalolsavam',
            'sports'       => 'Sports Meet',
            'kids_fest'    => 'Kids Fest',
            'teacher_fest' => 'Teacher Fest',
            'custom'       => 'Custom',
        ];
    }
}
