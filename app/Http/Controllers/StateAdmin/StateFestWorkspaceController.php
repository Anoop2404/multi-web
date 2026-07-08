<?php

namespace App\Http\Controllers\StateAdmin;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StateFestWorkspaceController extends Controller
{
    public function index()
    {
        $events = StateFestEvent::orderByDesc('starts_on')->paginate(20);

        return Inertia::render('StateAdmin/Fest/Index', [
            'events' => $events,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'state_program_id' => 'required|uuid',
            'name'             => 'required|string|max:255',
            'starts_on'        => 'nullable|date',
            'ends_on'          => 'nullable|date',
        ]);

        $event = StateFestEvent::create(array_merge($data, ['status' => 'draft']));

        return redirect()->route('admin.state.fest.show', $event)->with('success', 'State fest event created.');
    }

    public function show(StateFestEvent $event)
    {
        $approvedQualifiers = StateQualifierEntry::where('status', 'approved')
            ->whereHas('intake', fn ($q) => $q->where('state_program_id', $event->state_program_id))
            ->orderBy('item_code')
            ->limit(100)
            ->get();

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->with('participants')
            ->orderBy('item_code')
            ->limit(200)
            ->get();

        return Inertia::render('StateAdmin/Fest/Show', [
            'event'              => $event,
            'approvedQualifiers' => $approvedQualifiers,
            'registrations'      => $registrations,
        ]);
    }
}
