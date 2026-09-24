<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemSchedule;
use App\Models\State\StateVenue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Phase 5 of the State Kalotsav module — the item schedule and the clashes it creates.
 *
 * Clash detection is the reason the schedule exists as data rather than a printed sheet. Four kinds
 * matter at State level, and they are not the same as at Sahodaya level because the competing unit
 * is different:
 *
 *   participant — the same person in two overlapping items. The State's worst case, because a
 *                 Kalotsavam participant routinely enters several items and cannot be in two places.
 *   team        — a team whose members are individually entered elsewhere at the same time.
 *   venue       — two items on the same stage at once.
 *   sahodaya    — a Sahodaya with more items running at once than it can staff. Advisory rather
 *                 than an error: a large Sahodaya can genuinely cover four stages, a small one
 *                 cannot, and only the State office knows which.
 */
class StateScheduleService
{
    /**
     * The schedule for an event: every item, whether or not it has been given a time.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function scheduleFor(StateFestEvent $event): Collection
    {
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->orderBy('display_order')->orderBy('title')->get();

        $schedules = StateItemSchedule::where('state_event_id', $event->id)->get()->keyBy('item_id');
        $venues = StateVenue::where('state_event_id', $event->id)->get()->keyBy('id');

        $counts = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->selectRaw('item_id, count(*) as c')
            ->groupBy('item_id')->pluck('c', 'item_id');

        return $items->map(function (FestStateProgramItem $item) use ($schedules, $venues, $counts) {
            $schedule = $schedules->get($item->id);

            return [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'title' => $item->title,
                'class_group' => $item->class_group,
                'participants' => (int) ($counts[$item->id] ?? 0),
                'schedule_id' => $schedule?->id,
                'scheduled_on' => $schedule?->scheduled_on?->toDateString(),
                'reporting_at' => $this->hhmm($schedule?->reporting_at),
                'starts_at' => $this->hhmm($schedule?->starts_at),
                'ends_at' => $this->hhmm($schedule?->ends_at),
                'duration_minutes' => $schedule?->duration_minutes ?: $item->duration_minutes,
                'venue_id' => $schedule?->venue_id,
                'venue_name' => $venues->get($schedule?->venue_id)?->name,
                'is_public' => (bool) $schedule?->is_public,
                'is_scheduled' => (bool) $schedule?->isScheduled(),
            ];
        });
    }

    /** @param array<string, mixed> $data */
    public function save(StateFestEvent $event, FestStateProgramItem $item, array $data): StateItemSchedule
    {
        return StateItemSchedule::updateOrCreate(
            ['state_event_id' => $event->id, 'item_id' => $item->id],
            [
                'id' => StateItemSchedule::where('state_event_id', $event->id)->where('item_id', $item->id)->value('id') ?: (string) Str::uuid(),
                'state_id' => $event->state_id,
                'item_code' => $item->item_code,
                'scheduled_on' => $data['scheduled_on'] ?? null,
                'reporting_at' => $data['reporting_at'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'venue_id' => $data['venue_id'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? $item->duration_minutes,
                'is_public' => (bool) ($data['is_public'] ?? false),
                'notes' => $data['notes'] ?? null,
            ],
        );
    }

    /**
     * Every clash in the current schedule.
     *
     * @return array{participant: list<array<string,mixed>>, venue: list<array<string,mixed>>, sahodaya: list<array<string,mixed>>}
     */
    public function clashes(StateFestEvent $event): array
    {
        $scheduled = StateItemSchedule::where('state_event_id', $event->id)
            ->whereNotNull('scheduled_on')->whereNotNull('starts_at')->get();

        if ($scheduled->isEmpty()) {
            return ['participant' => [], 'venue' => [], 'sahodaya' => []];
        }

        $byItem = $scheduled->keyBy('item_id');

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->whereIn('item_id', $scheduled->pluck('item_id'))
            ->with('participants')
            ->get();

        return [
            'participant' => $this->participantClashes($registrations, $byItem),
            'venue' => $this->venueClashes($scheduled, $event),
            'sahodaya' => $this->sahodayaLoad($registrations, $byItem),
        ];
    }

    /**
     * The same person entered for two items whose slots overlap.
     *
     * Matched on name within a School, because the State's source of truth is a certified snapshot
     * of names, not a student registry — two different children called Athira Menon at the same
     * School would be a false positive, which is the right way round: a scrutineer would rather
     * check one extra row than miss a real clash.
     *
     * @return list<array<string, mixed>>
     */
    private function participantClashes(Collection $registrations, Collection $byItem): array
    {
        $appearances = [];

        foreach ($registrations as $registration) {
            $schedule = $byItem->get($registration->item_id);

            if (! $schedule) {
                continue;
            }

            foreach ($registration->participants as $participant) {
                // A standby is not competing, so their time is not contested.
                if (! $participant->isCompeting()) {
                    continue;
                }

                $key = strtolower(trim($participant->student_name)).'|'.($registration->school_id ?: '');

                $appearances[$key][] = [
                    'name' => $participant->student_name,
                    'sahodaya' => $registration->sahodaya_name ?: $registration->sahodaya_id,
                    'school' => $registration->school_name ?: $registration->school_id,
                    'item_code' => $registration->item_code,
                    'date' => $schedule->scheduled_on?->toDateString(),
                    'start' => $schedule->startMinutes(),
                    'end' => $schedule->endMinutes(),
                    'starts_at' => $this->hhmm($schedule->starts_at),
                    'is_team' => $registration->participants->count() > 1,
                ];
            }
        }

        $clashes = [];

        foreach ($appearances as $entries) {
            if (count($entries) < 2) {
                continue;
            }

            foreach ($this->overlappingPairs($entries) as [$a, $b]) {
                $clashes[] = [
                    'name' => $a['name'],
                    'sahodaya' => $a['sahodaya'],
                    'school' => $a['school'],
                    'date' => $a['date'],
                    'a' => "{$a['item_code']} at {$a['starts_at']}",
                    'b' => "{$b['item_code']} at {$b['starts_at']}",
                    // A team clash is worth distinguishing: moving one child is easy, moving a team
                    // of six is a different conversation.
                    'kind' => ($a['is_team'] || $b['is_team']) ? 'team' : 'participant',
                ];
            }
        }

        return $clashes;
    }

    /** Two items on one stage at the same time. @return list<array<string, mixed>> */
    private function venueClashes(Collection $scheduled, StateFestEvent $event): array
    {
        $venues = StateVenue::where('state_event_id', $event->id)->get()->keyBy('id');
        $clashes = [];

        foreach ($scheduled->whereNotNull('venue_id')->groupBy(fn ($s) => $s->venue_id.'|'.$s->scheduled_on?->toDateString()) as $group) {
            $entries = $group->map(fn (StateItemSchedule $s) => [
                'item_code' => $s->item_code,
                'start' => $s->startMinutes(),
                'end' => $s->endMinutes(),
                'starts_at' => $this->hhmm($s->starts_at),
                'venue' => $venues->get($s->venue_id)?->name ?? '—',
                'date' => $s->scheduled_on?->toDateString(),
            ])->values()->all();

            foreach ($this->overlappingPairs($entries) as [$a, $b]) {
                $clashes[] = [
                    'venue' => $a['venue'],
                    'date' => $a['date'],
                    'a' => "{$a['item_code']} at {$a['starts_at']}",
                    'b' => "{$b['item_code']} at {$b['starts_at']}",
                ];
            }
        }

        return $clashes;
    }

    /**
     * How many items each Sahodaya has running at once — advisory, not an error.
     *
     * A Sahodaya sending forty participants can cover four stages; one sending six cannot. Only the
     * State office knows which, so this reports the concurrency and leaves the judgement to them.
     *
     * @return list<array<string, mixed>>
     */
    private function sahodayaLoad(Collection $registrations, Collection $byItem): array
    {
        $rows = [];

        foreach ($registrations->groupBy('sahodaya_id') as $sahodayaId => $group) {
            $slots = $group->map(function (StateFestRegistration $r) use ($byItem) {
                $schedule = $byItem->get($r->item_id);

                return $schedule ? [
                    'item_code' => $r->item_code,
                    'date' => $schedule->scheduled_on?->toDateString(),
                    'start' => $schedule->startMinutes(),
                    'end' => $schedule->endMinutes(),
                    'starts_at' => $this->hhmm($schedule->starts_at),
                ] : null;
            })->filter()->unique('item_code')->values()->all();

            foreach ($this->overlappingPairs($slots) as [$a, $b]) {
                $rows[] = [
                    'sahodaya' => $group->first()->sahodaya_name ?: $sahodayaId,
                    'date' => $a['date'],
                    'a' => "{$a['item_code']} at {$a['starts_at']}",
                    'b' => "{$b['item_code']} at {$b['starts_at']}",
                ];
            }
        }

        return $rows;
    }

    /**
     * Pairs from a list whose slots overlap on the same date.
     *
     * Touching slots do not overlap — an item finishing at 10:00 and the next starting at 10:00 is
     * a schedule, not a clash.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    private function overlappingPairs(array $entries): array
    {
        $pairs = [];
        $count = count($entries);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $entries[$i];
                $b = $entries[$j];

                if (($a['date'] ?? null) !== ($b['date'] ?? null)) {
                    continue;
                }

                if ($a['start'] === null || $b['start'] === null) {
                    continue;
                }

                if ($a['start'] < $b['end'] && $b['start'] < $a['end']) {
                    $pairs[] = [$a, $b];
                }
            }
        }

        return $pairs;
    }

    /**
     * Green room list for a date: who is due on stage, in the order they perform.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function greenRoom(StateFestEvent $event, ?string $date = null): Collection
    {
        $schedules = StateItemSchedule::where('state_event_id', $event->id)
            ->whereNotNull('scheduled_on')
            ->when($date, fn ($q) => $q->whereDate('scheduled_on', $date))
            ->orderBy('scheduled_on')->orderBy('starts_at')->get();

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->whereIn('item_id', $schedules->pluck('item_id'))
            ->with('participants')->get()->groupBy('item_id');

        $venues = StateVenue::where('state_event_id', $event->id)->get()->keyBy('id');

        return $schedules->map(function (StateItemSchedule $s) use ($registrations, $venues) {
            $entries = ($registrations->get($s->item_id) ?? collect())
                // Performance order: chest number where one has been allocated, otherwise the
                // Sahodaya, so a sheet printed before numbering is still usable.
                ->sortBy(fn ($r) => $r->participants->first()->chest_number ?? $r->sahodaya_name)
                ->values();

            return [
                'item_code' => $s->item_code,
                'date' => $s->scheduled_on?->toDateString(),
                'reporting_at' => $this->hhmm($s->reporting_at),
                'starts_at' => $this->hhmm($s->starts_at),
                'venue' => $venues->get($s->venue_id)?->name,
                'entries' => $entries->map(fn (StateFestRegistration $r, int $i) => [
                    'order' => $i + 1,
                    'chest_number' => $r->participants->first()->chest_number,
                    'participants' => $r->participants->filter(fn (StateFestParticipant $p) => $p->isCompeting())
                        ->pluck('student_name')->implode(', '),
                    'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                    'school' => $r->school_name ?: $r->school_id,
                ])->values(),
            ];
        });
    }

    private function hhmm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
