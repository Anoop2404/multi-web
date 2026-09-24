<?php

namespace App\Services\State\Fest;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemSchedule;
use App\Models\State\StateVenue;
use Illuminate\Support\Collection;

/**
 * Phase 5 of the State Kalotsav module — the paper an event actually runs on.
 *
 * Every sheet here is printed once and then lived with for three days, so two things matter more
 * than layout. Rows appear in the order people will be called — chest number where one exists,
 * falling back to Sahodaya so a sheet printed before numbering is still usable. And both the
 * Sahodaya and the School appear on every row, because the person holding the sheet is looking for a
 * contingent, while the person queried about a result is asked about a school.
 */
class StatePrintService
{
    /**
     * @return array{title: string, subtitle: ?string, groups: list<array<string, mixed>>}
     */
    public function attendanceSheet(StateFestEvent $event, array $filters = []): array
    {
        return $this->sheet($event, $filters, 'Attendance Sheet', [
            ['label' => 'Sl', 'width' => '5%'],
            ['label' => 'Chest', 'width' => '9%'],
            ['label' => 'Participant / team', 'width' => '26%'],
            ['label' => 'Sahodaya', 'width' => '20%'],
            ['label' => 'School', 'width' => '20%'],
            ['label' => 'Present', 'width' => '10%'],
            ['label' => 'Signature', 'width' => '10%'],
        ], fn (array $row, int $i) => [
            $i + 1, $row['chest'], $row['participants'], $row['sahodaya'], $row['school'], '', '',
        ]);
    }

    /** Start and finish times per competitor — the stage's own record of what actually happened. */
    public function timesheet(StateFestEvent $event, array $filters = []): array
    {
        return $this->sheet($event, $filters, 'Timesheet', [
            ['label' => 'Sl', 'width' => '5%'],
            ['label' => 'Chest', 'width' => '10%'],
            ['label' => 'Participant / team', 'width' => '30%'],
            ['label' => 'Sahodaya', 'width' => '22%'],
            ['label' => 'Start', 'width' => '11%'],
            ['label' => 'Finish', 'width' => '11%'],
            ['label' => 'Initials', 'width' => '11%'],
        ], fn (array $row, int $i) => [
            $i + 1, $row['chest'], $row['participants'], $row['sahodaya'], '', '', '',
        ]);
    }

    /**
     * The judge's sheet. Deliberately carries no Sahodaya or School: a judge scores a performance,
     * and knowing which contingent it came from is exactly the information they should not have.
     */
    public function judgeSheet(StateFestEvent $event, array $filters = []): array
    {
        return $this->sheet($event, $filters, 'Judge Score Sheet', [
            ['label' => 'Sl', 'width' => '6%'],
            ['label' => 'Chest', 'width' => '14%'],
            ['label' => 'Order', 'width' => '10%'],
            ['label' => 'Score', 'width' => '20%'],
            ['label' => 'Remarks', 'width' => '50%'],
        ], fn (array $row, int $i) => [$i + 1, $row['chest'], $i + 1, '', ''], anonymous: true);
    }

    public function greenRoomSheet(StateFestEvent $event, array $filters = []): array
    {
        return $this->sheet($event, $filters, 'Green Room List', [
            ['label' => 'Order', 'width' => '8%'],
            ['label' => 'Chest', 'width' => '10%'],
            ['label' => 'Participant / team', 'width' => '30%'],
            ['label' => 'Sahodaya', 'width' => '22%'],
            ['label' => 'School', 'width' => '20%'],
            ['label' => 'Reported', 'width' => '10%'],
        ], fn (array $row, int $i) => [
            $i + 1, $row['chest'], $row['participants'], $row['sahodaya'], $row['school'], '',
        ]);
    }

    /**
     * ID and admit cards — one card per person, not one per entry.
     *
     * Chest numbers are unique per event, so a participant entered for four items holds four
     * numbers, one per entry. The card therefore lists every item with the number to wear for it:
     * handing the same person four cards is how three of them end up in a bag and the wrong number
     * is worn on stage.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function cards(StateFestEvent $event, array $filters = []): Collection
    {
        return $this->registrations($event, $filters)
            ->flatMap(fn (StateFestRegistration $r) => $r->participants
                ->filter(fn (StateFestParticipant $p) => $p->isCompeting())
                ->map(fn (StateFestParticipant $p) => [
                    'name' => $p->student_name,
                    'class_name' => $p->class_name,
                    'chest_number' => $p->chest_number,
                    'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                    'school' => $r->school_name ?: $r->school_id,
                    'item_code' => $r->item_code,
                ]))
            // Identity is name + school: two participants with the same name from different schools
            // are two people, and merging them would put one person's items on the other's card.
            ->groupBy(fn (array $entry) => $entry['name'].'|'.$entry['school'])
            ->map(fn (Collection $entries) => [
                'name' => $entries->first()['name'],
                'class_name' => $entries->first()['class_name'],
                'sahodaya' => $entries->first()['sahodaya'],
                'school' => $entries->first()['school'],
                // Shown large when there is only one — the common case, and the number a gate
                // marshal reads.
                'chest_number' => $entries->count() === 1 ? $entries->first()['chest_number'] : null,
                'entries' => $entries
                    ->sortBy('item_code')
                    ->map(fn (array $e) => ['item_code' => $e['item_code'], 'chest_number' => $e['chest_number']])
                    ->values()->all(),
            ])
            ->sortBy(fn (array $c) => [$c['sahodaya'], $c['school'], $c['name']])
            ->values();
    }

    /**
     * @param  list<array{label: string, width: string}>  $columns
     * @return array{title: string, subtitle: ?string, columns: list<array<string,mixed>>, groups: list<array<string, mixed>>}
     */
    private function sheet(StateFestEvent $event, array $filters, string $title, array $columns, callable $mapper, bool $anonymous = false): array
    {
        $schedules = StateItemSchedule::where('state_event_id', $event->id)->get()->keyBy('item_id');
        $venues = StateVenue::where('state_event_id', $event->id)->get()->keyBy('id');

        $groups = $this->registrations($event, $filters)
            ->groupBy('item_id')
            ->map(function (Collection $registrations, $itemId) use ($schedules, $venues, $mapper, $anonymous) {
                $schedule = $schedules->get($itemId);

                $rows = $registrations
                    // Called in chest-number order; a sheet printed before numbering still works
                    // because it falls back to the Sahodaya.
                    ->sortBy(fn (StateFestRegistration $r) => $r->participants->first()?->chest_number
                        ?? $r->sahodaya_name)
                    ->values()
                    ->map(fn (StateFestRegistration $r) => [
                        'chest' => $r->participants->first()?->chest_number ?? '',
                        'participants' => $r->participants->filter(fn ($p) => $p->isCompeting())
                            ->pluck('student_name')->implode(', '),
                        'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                        'school' => $r->school_name ?: $r->school_id,
                    ]);

                return [
                    'item_code' => $registrations->first()->item_code,
                    'when' => $schedule?->scheduled_on?->toDateString(),
                    'starts_at' => $schedule?->starts_at ? substr($schedule->starts_at, 0, 5) : null,
                    'venue' => $venues->get($schedule?->venue_id)?->name,
                    'count' => $rows->count(),
                    'rows' => $rows->values()->map(fn (array $row, int $i) => $mapper($row, $i))->all(),
                ];
            })
            ->sortBy('item_code')->values()->all();

        return [
            'title' => $title,
            'subtitle' => $anonymous ? 'Chest numbers only — the panel does not see which Sahodaya a performance came from' : null,
            'columns' => $columns,
            'groups' => $groups,
        ];
    }

    /** @return Collection<int, StateFestRegistration> */
    private function registrations(StateFestEvent $event, array $filters): Collection
    {
        return StateFestRegistration::where('state_event_id', $event->id)
            ->where('status', 'approved')
            ->when($filters['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->when($filters['sahodaya_id'] ?? null, fn ($q, $v) => $q->where('sahodaya_id', $v))
            ->with('participants')
            ->get();
    }
}
