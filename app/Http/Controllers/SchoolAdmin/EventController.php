<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Event;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $sort = in_array($request->string('sort')->toString(), ['title', 'start_date', 'venue', 'created_at'], true)
            ? $request->string('sort')->toString()
            : 'start_date';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $events = Event::where('tenant_id', $this->school->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%");
                });
            })
            ->when($status === 'upcoming', fn ($query) => $query->whereDate('start_date', '>=', today()))
            ->when($status === 'past', fn ($query) => $query->whereDate('start_date', '<', today()))
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        return $this->inertia('School/Events/Index', [
            'events' => $events,
            'filters' => compact('search', 'status', 'sort', 'dir'),
        ]);
    }

    public function create()
    {
        return $this->inertia('School/Events/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'venue' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:4096',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);

        if ($request->hasFile('image')) {
            $data['image'] = TenantStorage::storeSiteMedia($request->file('image'), $this->school->id);
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'venue' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = TenantStorage::storeSiteMedia($request->file('image'), $this->school->id);
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
