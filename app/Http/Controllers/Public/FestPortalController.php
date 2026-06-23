<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Services\Events\EventContext;
use Illuminate\Http\Request;

class FestPortalController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();

        $events = FestEvent::where('tenant_id', $tenant->id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'event_start', 'venue', 'status', 'results_published']);

        return $this->renderPublic('public.fest.index', $tenant, compact('events'));
    }

    public function show(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        return $this->renderPublic('public.fest.show', $tenant, [
            'event' => $event,
            'pageSeo' => ['title' => $event->title.' — '.$tenant->name],
        ]);
    }

    public function schedule(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        $schedules = FestSchedule::where('event_id', $event->id)
            ->with(['item', 'participant.student', 'participant.teacher'])
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get();

        return $this->renderPublic('public.fest.schedule', $tenant, compact('event', 'schedules'));
    }

    public function live(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $ctx = EventContext::for($event);

        $nowSlot = FestSchedule::where('event_id', $event->id)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderByDesc('scheduled_at')
            ->with(['item', 'participant.student'])
            ->first();

        return $this->renderPublic('public.fest.live', $tenant, [
            'event'            => $event,
            'scoreboard'       => $ctx->scoreboardBySchool(),
            'houseScoreboard'  => $ctx->scoreboardByHouse(),
            'nowSlot'          => $nowSlot,
        ]);
    }

    public function search(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $q = trim($request->query('q', ''));

        $results = collect();
        if (strlen($q) >= 1) {
            $results = FestParticipant::whereHas('registration', fn ($r) => $r
                ->where('event_id', $event->id)
                ->where('status', 'approved'))
                ->with(['student', 'teacher', 'registration.item'])
                ->where(function ($query) use ($q) {
                    if (is_numeric($q)) {
                        $query->where('chest_no', (int) $q);
                    } else {
                        $query->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                            ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$q}%"));
                    }
                })
                ->limit(30)
                ->get();
        }

        return $this->renderPublic('public.fest.search', $tenant, compact('event', 'q', 'results'));
    }

    public function participant(int $eventId, int $chestNo)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        $participant = FestParticipant::whereHas('registration', fn ($r) => $r
            ->where('event_id', $event->id))
            ->where('chest_no', $chestNo)
            ->with(['student', 'teacher', 'registration.item', 'group'])
            ->firstOrFail();

        $mark = FestMark::where('participant_id', $participant->id)->first();
        $schedule = FestSchedule::where('participant_id', $participant->id)->first();

        return $this->renderPublic('public.fest.participant', $tenant, compact(
            'event', 'participant', 'mark', 'schedule', 'chestNo'
        ));
    }

    private function findEvent(string $tenantId, int $eventId): FestEvent
    {
        return FestEvent::where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->firstOrFail();
    }
}
