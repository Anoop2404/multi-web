<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\FestAthleticRecord;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRecordBreak;
use App\Models\FestSchedule;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\EventContext;
use App\Services\Events\FestPublicVisibilityService;
use Illuminate\Http\Request;

class FestPortalController extends Controller
{
    use RendersPublicPages;

    public function __construct(
        private FestPublicVisibilityService $visibility,
    ) {}

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

        $items = FestEventItem::where('event_id', $event->id)
            ->orderBy('display_order')
            ->get(['id', 'title', 'stage_type', 'category']);

        return $this->renderPublic('public.fest.show', $tenant, [
            'event' => $event,
            'items' => $items,
            'pageSeo' => ['title' => $event->title.' — '.$tenant->name],
        ]);
    }

    public function schedule(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        EventLifecycleGate::allowPublicSchedule($event);

        $schedules = $this->mapScheduleRows($event);

        return $this->renderPublic('public.fest.schedule', $tenant, compact('event', 'schedules'));
    }

    public function itemSchedule(int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_if($item->event_id !== $event->id, 404);
        EventLifecycleGate::allowPublicSchedule($event);

        $schedules = $this->mapScheduleRows($event, $item->id);

        return $this->renderPublic('public.fest.item-schedule', $tenant, compact('event', 'item', 'schedules'));
    }

    public function itemResults(int $eventId, FestEventItem $item)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        abort_if($item->event_id !== $event->id, 404);
        abort_unless($event->results_published, 404);

        $marks = FestMark::where('event_id', $event->id)
            ->where('item_id', $item->id)
            ->with(['participant.student', 'participant.teacher', 'participant.registration.school'])
            ->orderBy('position')
            ->orderByDesc('score')
            ->get()
            ->map(fn (FestMark $m) => [
                'reference' => $m->participant
                    ? $this->visibility->publicReference($event, $m->participant)
                    : '—',
                'name'      => $m->participant?->student?->name ?? $m->participant?->teacher?->name,
                'school'    => $m->participant?->registration?->school?->name,
                'position'  => $m->position,
                'grade'     => $m->grade,
                'score'     => $m->score,
                'result'    => trim(($m->measurement_value ?? '').' '.($m->measurement_unit ?? '')),
            ]);

        return $this->renderPublic('public.fest.item-results', $tenant, compact('event', 'item', 'marks'));
    }

    public function live(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        return $this->renderPublic('public.fest.live', $tenant, array_merge(
            ['event' => $event],
            $this->livePayload($event)
        ));
    }

    public function liveData(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        return response()->json($this->livePayload($event));
    }

    /** @return array<string, mixed> */
    private function livePayload(FestEvent $event): array
    {
        $ctx = EventContext::for($event);

        $nowSlot = FestSchedule::where('event_id', $event->id)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderByDesc('scheduled_at')
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.event', 'participant.registration.item'])
            ->first();

        $nowPerforming = null;
        if ($nowSlot?->participant) {
            $nowPerforming = $this->visibility->formatPublicParticipant($event, $nowSlot->participant, $nowSlot);
            $nowPerforming['item_title'] = $nowSlot->item?->title;
        }

        return [
            'scoreboard'      => $ctx->scoreboardBySchool(),
            'houseScoreboard' => $ctx->scoreboardByHouse(),
            'nowPerforming'   => $nowPerforming,
            'athleticRecords' => $this->publicAthleticRecords($event),
            'recentBreaks'    => $this->recentRecordBreaks($event),
            'refreshedAt'     => now()->toIso8601String(),
        ];
    }

    public function records(int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        return $this->renderPublic('public.fest.records', $tenant, [
            'event'   => $event,
            'records' => $this->publicAthleticRecords($event),
            'breaks'  => $this->recentRecordBreaks($event, 50),
        ]);
    }

    public function search(Request $request, int $eventId)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);
        $q = trim($request->query('q', ''));

        $results = collect();
        if (strlen($q) >= 1) {
            $query = FestParticipant::whereHas('registration', fn ($r) => $r
                ->where('event_id', $event->id)
                ->where('status', 'approved'))
                ->with(['student', 'teacher', 'registration.item', 'registration.event']);

            if (ctype_digit($q)) {
                $query->where('chest_no', (int) $q);
            } elseif (preg_match('/^[A-Za-z]-\d+$/', $q)) {
                $query->where('level_registration_number', strtoupper($q));
            } elseif ($this->visibility->allowNameSearch($event)) {
                $query->where(function ($inner) use ($q) {
                    $inner->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$q}%"));
                });
            }

            $results = $query->limit(30)->get()
                ->map(fn (FestParticipant $p) => $this->visibility->formatPublicParticipant($event, $p));
        }

        return $this->renderPublic('public.fest.search', $tenant, [
            'event'       => $event,
            'q'           => $q,
            'results'     => $results,
            'searchHint'  => $this->visibility->searchPlaceholder($event),
            'nameSearch'  => $this->visibility->allowNameSearch($event),
        ]);
    }

    public function participant(int $eventId, string $ref)
    {
        $tenant = $this->resolveTenant();
        $event = $this->findEvent($tenant->id, $eventId);

        $participant = $this->visibility->findParticipantByRef($event, $ref);
        abort_unless($participant, 404);

        $mark = FestMark::where('participant_id', $participant->id)->first();
        $schedule = FestSchedule::where('participant_id', $participant->id)->first();

        $public = $this->visibility->formatPublicParticipant($event, $participant, $schedule, $mark);

        return $this->renderPublic('public.fest.participant', $tenant, compact(
            'event', 'public', 'participant', 'schedule', 'mark'
        ));
    }

    /** @return list<array<string, mixed>> */
    private function mapScheduleRows(FestEvent $event, ?int $itemId = null): array
    {
        $query = FestSchedule::where('event_id', $event->id)
            ->with(['item', 'participant.student', 'participant.teacher', 'participant.registration.item', 'participant.registration.event']);

        if ($itemId) {
            $query->where('item_id', $itemId);
        }

        return $query->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get()
            ->map(function (FestSchedule $row) use ($event) {
                return [
                    'scheduled_at' => $row->scheduled_at,
                    'item_id'      => $row->item_id,
                    'item_title'   => $row->item?->title,
                    'stage'        => $row->stage,
                    'sort_order'   => $row->sort_order,
                    'participant'  => $row->participant
                        ? $this->visibility->formatPublicParticipant($event, $row->participant, $row)
                        : null,
                ];
            })
            ->all();
    }

    private function findEvent(string $tenantId, int $eventId): FestEvent
    {
        return FestEvent::where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function publicAthleticRecords(FestEvent $event): array
    {
        if (! $event->record_tracking_enabled) {
            return [];
        }

        return FestAthleticRecord::where('event_id', $event->id)
            ->with('item')
            ->orderBy('item_id')
            ->get()
            ->map(fn (FestAthleticRecord $r) => [
                'item'         => $r->item?->title,
                'class_group'  => strtoupper($r->class_group),
                'gender'       => ucfirst($r->gender),
                'value'        => $r->record_value,
                'unit'         => $r->record_unit,
                'holder'       => $r->holder_name,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentRecordBreaks(FestEvent $event, int $limit = 5): array
    {
        if (! $event->record_tracking_enabled) {
            return [];
        }

        return FestRecordBreak::where('event_id', $event->id)
            ->with(['item', 'participant.student'])
            ->orderByDesc('broken_at')
            ->limit($limit)
            ->get()
            ->map(fn (FestRecordBreak $b) => [
                'item'        => $b->item?->title,
                'name'        => $b->participant?->student?->name ?? $b->participant?->teacher?->name,
                'new_value'   => $b->new_value,
                'unit'        => $b->record_unit,
                'prize_label' => $b->prize_label,
                'broken_at'   => $b->broken_at?->format('d M Y H:i'),
            ])
            ->all();
    }
}
