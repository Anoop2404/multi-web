<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestMarkSaveService;
use App\Services\Events\FestSportsAutoRankService;
use App\Support\FestPageActivity;
use Illuminate\Http\Request;

class FestMarkEntryController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('status', 'approved')
            ->with(['item', 'school', 'participants.student', 'participants.teacher'])
            ->get();

        $marks = FestMark::where('event_id', $event->id)->get()->keyBy('participant_id');

        return $this->inertia('Sahodaya/Events/MarkEntry', $this->withEventActivity($event, FestPageActivity::MARKS, [
            'event'         => $event,
            'registrations' => $registrations,
            'marks'         => $marks,
        ]));
    }

    public function store(Request $request, string $tenantId, FestEvent $event, FestMarkSaveService $markSave, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        EventLifecycleGate::allowMarkEntry($event);

        $data = $request->validate([
            'participant_id'    => 'required|exists:fest_participants,id',
            'item_id'           => 'required|exists:fest_event_items,id',
            'grade'             => 'nullable|in:A,A+,B,C',
            'position'          => 'nullable|integer|min:1|max:255',
            'score'             => 'nullable|numeric|min:0',
            'measurement_value' => 'nullable|string|max:50',
            'measurement_unit'  => 'nullable|string|max:20',
        ]);

        $result = $markSave->save($event, $data, $request->user()->id);

        $audit->festEvent($event, FestPageActivity::MARKS, 'fest.mark.saved', "Mark saved for participant #{$data['participant_id']}", [
            'participant_id' => $data['participant_id'],
            'item_id'        => $data['item_id'],
        ]);

        return back()->with('success', $result['message']);
    }

    public function autoRankItem(string $tenantId, FestEvent $event, FestEventItem $item, FestSportsAutoRankService $ranker)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);
        abort_unless($event->event_type === 'sports', 422, 'Auto-rank applies to sports events only.');

        $result = $ranker->rankItem($event, $item);

        return back()->with('success', "Auto-ranked {$result['ranked']} athlete(s) for {$result['item_title']}.");
    }
}
