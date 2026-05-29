<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends SchoolAdminController
{
    public function index()
    {
        $events = Event::where('tenant_id', $this->school->id)
            ->orderBy('start_date')
            ->paginate(20);

        return $this->inertia('School/Events/Index', compact('events'));
    }

    public function create()
    {
        return $this->inertia('School/Events/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'venue'       => 'nullable|string|max:255',
            'image'       => 'nullable|image|max:4096',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data['slug']      = Str::slug($data['title']) . '-' . Str::random(5);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('events/' . $this->school->id, 's3');
        }

        Event::create($data);

        return redirect("/school-admin/{$this->school->id}/events")->with('success', 'Event created.');
    }

    public function edit(string $tenantId, Event $event)
    {
        abort_if($event->tenant_id !== $this->school->id, 403);
        return $this->inertia('School/Events/Edit', compact('event'));
    }

    public function update(Request $request, string $tenantId, Event $event)
    {
        abort_if($event->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'venue'       => 'nullable|string|max:255',
            'image'       => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('events/' . $this->school->id, 's3');
        }

        $event->update($data);

        return redirect("/school-admin/{$this->school->id}/events")->with('success', 'Event updated.');
    }

    public function destroy(string $tenantId, Event $event)
    {
        abort_if($event->tenant_id !== $this->school->id, 403);
        $event->delete();
        return back()->with('success', 'Event deleted.');
    }
}
