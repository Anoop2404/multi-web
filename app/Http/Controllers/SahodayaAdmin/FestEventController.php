<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Services\Events\EventContext;
use Illuminate\Http\Request;

class FestEventController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $type = $request->query('type');

        $q = FestEvent::forTenant($this->sahodaya->id)
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start');

        if ($type) {
            $q->ofType($type);
        }

        return $this->inertia('Sahodaya/Events/Index', [
            'events' => $q->get(),
            'filter' => ['type' => $type],
            'eventTypes' => $this->eventTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'event_type'         => 'required|in:kalolsavam,sports,kids_fest,teacher_fest,custom',
            'academic_year_id'   => 'nullable|exists:academic_years,id',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date',
            'event_start'        => 'nullable|date',
            'event_end'          => 'nullable|date',
            'venue'              => 'nullable|string|max:255',
            'fee_type'           => 'nullable|in:none,flat_school,per_participant,per_item',
            'fee_amount'         => 'nullable|numeric|min:0',
            'description'        => 'nullable|string',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';

        if (empty($data['academic_year_id'])) {
            $data['academic_year_id'] = AcademicYearRecord::where('tenant_id', $this->sahodaya->id)
                ->where('is_active', true)->value('id');
        }

        $event = FestEvent::create($data);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}")
            ->with('success', "Event \"{$event->title}\" created.");
    }

    public function show(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load(['items', 'academicYear', 'childEvents', 'parentEvent']);
        $ctx = EventContext::for($event);

        return $this->inertia('Sahodaya/Events/Show', [
            'event'      => $event,
            'scoreboard' => $ctx->scoreboardBySchool(),
        ]);
    }

    public function update(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'event_type'         => 'required|in:kalolsavam,sports,kids_fest,teacher_fest,custom',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date',
            'event_start'        => 'nullable|date',
            'event_end'          => 'nullable|date',
            'venue'              => 'nullable|string|max:255',
            'fee_type'           => 'nullable|in:none,flat_school,per_participant,per_item',
            'fee_amount'         => 'nullable|numeric|min:0',
            'status'             => 'required|in:draft,published,registration_open,ongoing,completed,cancelled',
            'results_published'  => 'boolean',
            'description'        => 'nullable|string',
        ]);

        $event->update($data);

        return back()->with('success', 'Event updated.');
    }

    public function destroy(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        $event->delete();

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events")
            ->with('success', 'Event deleted.');
    }

    public function spawnCascade(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $child = app(\App\Services\Events\FestCascadeService::class)
            ->spawnChildEvent($event, $data['title']);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$child->id}")
            ->with('success', 'Child event created from parent.');
    }

    public function storeItem(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'category'         => 'nullable|in:music,dance,drama,literary,sports,general',
            'participant_type' => 'nullable|in:individual,group,team',
            'gender'           => 'nullable|in:male,female,mixed,open',
            'class_group'      => 'nullable|in:lp,up,hs,hss,open',
            'max_per_school'   => 'nullable|integer|min:1',
            'qualify_count'    => 'nullable|integer|min:1',
        ]);

        $data['event_id'] = $event->id;
        $data['display_order'] = ($event->items()->max('display_order') ?? 0) + 1;
        $data['participant_type'] = $data['participant_type'] ?? 'individual';

        FestEventItem::create($data);

        return back()->with('success', 'Item added.');
    }

    public function destroyItem(string $tenantId, FestEvent $event, FestEventItem $item)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 403);
        $item->delete();

        return back()->with('success', 'Item removed.');
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
