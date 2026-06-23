<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestAppeal;
use App\Models\FestCateringOrder;
use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Services\Events\EventContext;
use Illuminate\Http\Request;

class FestEventPortalController extends SchoolAdminController
{
    public function house(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $assignment = \App\Models\FestHouseSchool::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with('house')
            ->first();

        return $this->inertia('School/Events/House', [
            'event'           => $event->only('id', 'title', 'status'),
            'house'           => $assignment?->house,
            'houseScoreboard' => EventContext::for($event)->scoreboardByHouse(),
        ]);
    }

    public function catering(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $orders = FestCateringOrder::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->orderByDesc('meal_date')
            ->get();

        return $this->inertia('School/Events/Catering', [
            'event'  => $event->only('id', 'title'),
            'orders' => $orders,
        ]);
    }

    public function storeCatering(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = $request->validate([
            'meal_date'  => 'required|date',
            'meal_type'  => 'required|in:breakfast,lunch,dinner,snacks',
            'head_count' => 'required|integer|min:1|max:5000',
            'notes'      => 'nullable|string|max:500',
        ]);

        FestCateringOrder::create(array_merge($data, [
            'event_id'              => $event->id,
            'school_id'             => $this->school->id,
            'status'                => 'requested',
            'submitted_by_user_id'  => $request->user()->id,
        ]));

        return back()->with('success', 'Meal request submitted.');
    }

    public function storeAppeal(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = $request->validate([
            'participant_id' => 'required|exists:fest_participants,id',
            'reason'         => 'required|string|max:2000',
        ]);

        $participant = FestParticipant::findOrFail($data['participant_id']);
        abort_if($participant->registration->school_id !== $this->school->id, 403);
        abort_if($participant->registration->event_id !== $event->id, 403);

        FestAppeal::create([
            'event_id'              => $event->id,
            'participant_id'        => $participant->id,
            'reason'                => $data['reason'],
            'status'                => 'pending',
            'submitted_by_user_id'  => $request->user()->id,
        ]);

        return back()->with('success', 'Appeal submitted.');
    }

    /** @return \Illuminate\Http\RedirectResponse */
    public function festHub(string $tenantId)
    {
        $event = FestEvent::where('tenant_id', $this->school->parent_id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->orderByDesc('event_start')
            ->first();

        if (! $event) {
            return redirect("/school-admin/{$this->school->id}/programs/kalotsav/registration");
        }

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with(['item', 'participants.student'])
            ->get();

        return $this->inertia('School/Events/FestHub', [
            'event'         => $event,
            'registrations' => $registrations,
        ]);
    }
}
