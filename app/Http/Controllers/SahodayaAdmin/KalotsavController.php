<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\KalotsavCategory;
use App\Models\KalotsavEvent;
use App\Models\KalotsavResult;
use App\Models\Tenant;
use Illuminate\Http\Request;

class KalotsavController extends SahodayaAdminController
{
    public function index()
    {
        $events = KalotsavEvent::where('tenant_id', $this->sahodaya->id)
            ->withCount(['categories', 'results'])
            ->orderByDesc('event_date')
            ->get();

        return $this->inertia('Sahodaya/Kalotsav/Index', compact('events'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'type'          => 'nullable|string|max:100',
            'academic_year' => 'nullable|string|max:20',
            'event_date'    => 'nullable|date',
            'venue'         => 'nullable|string|max:255',
            'description'   => 'nullable|string',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['is_active'] = true;

        $event = KalotsavEvent::create($data);
        return redirect("/sahodaya-admin/{$this->sahodaya->id}/kalotsav/{$event->id}")
            ->with('success', "Event \"{$event->name}\" created.");
    }

    public function show(string $tenantId, KalotsavEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load(['categories.results']);

        $memberSchools = Tenant::where('parent_id', $this->sahodaya->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $scoreboard = $event->scoreboardBySchool();

        return $this->inertia('Sahodaya/Kalotsav/Show', compact('event', 'memberSchools', 'scoreboard'));
    }

    public function update(Request $request, string $tenantId, KalotsavEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'type'               => 'nullable|string|max:100',
            'academic_year'      => 'nullable|string|max:20',
            'event_date'         => 'nullable|date',
            'venue'              => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'is_active'          => 'boolean',
            'results_published'  => 'boolean',
        ]);

        $event->update($data);
        return back()->with('success', 'Event updated.');
    }

    public function destroy(string $tenantId, KalotsavEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        $event->delete();
        return redirect("/sahodaya-admin/{$this->sahodaya->id}/kalotsav")->with('success', 'Event deleted.');
    }

    public function storeCategory(Request $request, string $tenantId, KalotsavEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'group'         => 'nullable|string|max:100',
            'max_points'    => 'nullable|integer|min:0',
            'display_order' => 'nullable|integer',
        ]);

        $data['kalotsav_event_id'] = $event->id;
        $data['display_order']    ??= $event->categories()->max('display_order') + 1;

        KalotsavCategory::create($data);
        return back()->with('success', 'Category added.');
    }

    public function destroyCategory(string $tenantId, KalotsavEvent $event, KalotsavCategory $category)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($category->kalotsav_event_id !== $event->id, 403);
        $category->delete();
        return back()->with('success', 'Category removed.');
    }

    public function storeResult(Request $request, string $tenantId, KalotsavEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'kalotsav_category_id' => 'required|exists:kalotsav_categories,id',
            'school_tenant_id'     => 'required|exists:tenants,id',
            'school_name'          => 'required|string|max:255',
            'position'             => 'nullable|string|max:50',
            'points'               => 'nullable|numeric|min:0',
            'grade'                => 'nullable|string|max:50',
            'notes'                => 'nullable|string',
        ]);

        $data['kalotsav_event_id'] = $event->id;

        KalotsavResult::updateOrCreate(
            [
                'kalotsav_event_id'    => $event->id,
                'kalotsav_category_id' => $data['kalotsav_category_id'],
                'school_tenant_id'     => $data['school_tenant_id'],
            ],
            $data
        );

        return back()->with('success', 'Result saved.');
    }

    public function destroyResult(string $tenantId, KalotsavEvent $event, KalotsavResult $result)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($result->kalotsav_event_id !== $event->id, 403);
        $result->delete();
        return back()->with('success', 'Result removed.');
    }
}
