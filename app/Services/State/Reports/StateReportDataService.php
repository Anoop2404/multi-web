<?php

namespace App\Services\State\Reports;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateAttendance;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\State\StateSahodaya;
use App\Models\StateRemittance;
use App\Services\State\Fest\StateSlotService;
use InvalidArgumentException;

/**
 * Phase 8 of the State Kalotsav module — the data behind the menu-reachable State reports.
 *
 * Every query reads State operational tables only. The State module does not open Sahodaya tenant
 * databases for its own reporting: the certified qualifier snapshot is its source of truth, which is
 * also what makes these reports reproducible after an event, when a tenant may have been renamed,
 * deactivated or promoted.
 *
 * Grouping is always on the canonical Sahodaya identity, never the raw submission key, so a Sahodaya
 * promoted mid-season appears once. School is carried through every row as a displayed value —
 * the State competes Sahodaya against Sahodaya, but a participant's School is never lost.
 *
 * @return array{title: string, headers: list<string>, rows: list<list<mixed>>}
 */
class StateReportDataService
{
    /** @param array<string, mixed> $filters */
    public function build(string $reportId, StateFestEvent $event, array $filters = []): array
    {
        return match ($reportId) {
            'registration-master'    => $this->registrationMaster($event, $filters),
            'sahodaya-participation' => $this->sahodayaParticipation($event, $filters),
            'sahodaya-school-matrix' => $this->sahodayaSchoolMatrix($event, $filters),
            'item-counts'            => $this->itemCounts($event, $filters),
            'item-participants'      => $this->itemParticipants($event, $filters),
            'student-wise'           => $this->studentWise($event, $filters),
            'unique-participants'    => $this->uniqueParticipants($event, $filters),
            'slot-usage'             => $this->slotUsage($event, $filters),
            'slot-violations'        => $this->slotViolations($event),
            'pending-approvals'      => $this->pendingApprovals($event, $filters),
            'attendance-status'      => $this->attendanceStatus($event, $filters),
            'mark-entry-status'      => $this->markEntryStatus($event, $filters),
            'overall-sahodaya-ranking' => $this->sahodayaRanking($event),
            'category-sahodaya-points' => $this->categorySahodayaPoints($event),
            'school-contribution'    => $this->schoolContribution($event, $filters),
            'item-wise-results'      => $this->itemWiseResults($event, $filters),
            'individual-championship' => $this->individualChampionship($event),
            'item-schedule'          => $this->itemSchedule($event),
            'schedule-clashes'       => $this->scheduleClashes($event),
            'sahodaya-fee-summary'   => $this->feeSummary($event),
            default => throw new InvalidArgumentException("No State report data for \"{$reportId}\"."),
        };
    }

    /** Registrations for this event, with the standard Sahodaya → School filters applied. */
    private function registrationsQuery(StateFestEvent $event, array $filters)
    {
        return StateFestRegistration::where('state_event_id', $event->id)
            ->when($filters['sahodaya_id'] ?? null, fn ($q, $id) => $q->where('sahodaya_id', $id))
            ->when($filters['school_id'] ?? null, fn ($q, $id) => $q->where('school_id', $id))
            ->when($filters['item_id'] ?? null, fn ($q, $id) => $q->where('item_id', $id))
            ->when($filters['origin'] ?? null, fn ($q, $origin) => $q->whereIn(
                'sahodaya_id',
                StateSahodaya::where('origin', $origin)->pluck('id'),
            ));
    }

    private function registrationMaster(StateFestEvent $event, array $filters): array
    {
        $rows = $this->registrationsQuery($event, $filters)
            ->orderBy('sahodaya_name')->orderBy('school_name')->orderBy('item_code')
            ->get()
            ->map(fn (StateFestRegistration $r) => [
                $r->sahodaya_name ?: $r->sahodaya_id,
                $r->school_name ?: $r->school_id,
                $r->item_code,
                $this->participantNames($r),
                $r->status,
            ])->all();

        return [
            'title' => 'State Registration Master List',
            'headers' => ['Sahodaya', 'School', 'Item', 'Participant(s)', 'Status'],
            'rows' => $rows,
        ];
    }

    private function sahodayaParticipation(StateFestEvent $event, array $filters): array
    {
        $registrations = $this->registrationsQuery($event, $filters)->get();
        $directory = StateSahodaya::whereIn('id', $registrations->pluck('sahodaya_id')->filter()->unique())->get()->keyBy('id');

        $rows = $registrations->groupBy('sahodaya_id')->map(function ($group, $sahodayaId) use ($directory) {
            $sahodaya = $directory->get($sahodayaId);

            return [
                // A registration can exist without an intake (created directly), and so without a
                // Sahodaya. Naming that case keeps it visible and countable instead of rendering a
                // blank row that looks like corrupt data.
                $sahodaya?->name ?: ($group->first()->sahodaya_name ?: ($sahodayaId ?: 'Unattributed — no submitting Sahodaya')),
                $sahodaya?->district ?? '',
                // Origin, not current tenancy: "arrived from outside" stays true after promotion.
                $sahodaya?->origin === StateSahodaya::ORIGIN_EXTERNAL ? 'Outside' : 'On platform',
                $group->pluck('school_id')->filter()->unique()->count(),
                $group->count(),
                $group->pluck('item_id')->filter()->unique()->count(),
                $group->where('status', 'approved')->count(),
            ];
        })->sortBy(fn ($r) => $r[0])->values()->all();

        return [
            'title' => 'Sahodaya-wise Registration Summary',
            'headers' => ['Sahodaya', 'District', 'Source', 'Schools', 'Registrations', 'Items', 'Approved'],
            'rows' => $rows,
        ];
    }

    private function sahodayaSchoolMatrix(StateFestEvent $event, array $filters): array
    {
        $rows = $this->registrationsQuery($event, $filters)->get()
            ->groupBy(fn ($r) => ($r->sahodaya_name ?: $r->sahodaya_id).'||'.($r->school_name ?: $r->school_id))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    $first->sahodaya_name ?: ($first->sahodaya_id ?: 'Unattributed'),
                    $first->school_name ?: $first->school_id,
                    $group->count(),
                    $group->pluck('item_id')->filter()->unique()->count(),
                    $group->where('status', 'approved')->count(),
                ];
            })->sortBy(fn ($r) => [$r[0], $r[1]])->values()->all();

        return [
            'title' => 'Sahodaya × School Participation Matrix',
            'headers' => ['Sahodaya', 'School', 'Registrations', 'Items', 'Approved'],
            'rows' => $rows,
        ];
    }

    private function itemCounts(StateFestEvent $event, array $filters): array
    {
        $registrations = $this->registrationsQuery($event, $filters)->get()->groupBy('item_id');
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->orderBy('display_order')->orderBy('title')->get();

        $rows = $items->map(function (FestStateProgramItem $item) use ($registrations) {
            $group = $registrations->get($item->id) ?? collect();

            return [
                $item->item_code,
                $item->title,
                $item->class_group,
                $group->pluck('sahodaya_id')->filter()->unique()->count(),
                $group->count(),
            ];
        })->all();

        return [
            'title' => 'Item List & Registration Counts',
            'headers' => ['Code', 'Item', 'Category', 'Sahodayas', 'Participants'],
            'rows' => $rows,
        ];
    }

    private function itemParticipants(StateFestEvent $event, array $filters): array
    {
        $rows = $this->registrationsQuery($event, $filters)
            ->orderBy('item_code')->orderBy('sahodaya_name')
            ->get()
            ->map(fn (StateFestRegistration $r) => [
                $r->item_code,
                $this->participantNames($r),
                $r->sahodaya_name ?: $r->sahodaya_id,
                $r->school_name ?: $r->school_id,
                $r->status,
            ])->all();

        return [
            'title' => 'Item-wise Participant List',
            'headers' => ['Item', 'Participant(s)', 'Sahodaya', 'School', 'Status'],
            'rows' => $rows,
        ];
    }

    private function studentWise(StateFestEvent $event, array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $rows = $this->registrationsQuery($event, $filters)->with('participants')->get()
            ->flatMap(function (StateFestRegistration $r) {
                $participants = $r->participants ?? collect();

                return $participants->isEmpty()
                    ? [[null, $r]]
                    : $participants->map(fn ($p) => [$p, $r])->all();
            })
            ->filter(fn ($pair) => $search === '' || str_contains(strtolower((string) ($pair[0]->student_name ?? '')), strtolower($search)))
            ->groupBy(fn ($pair) => ($pair[0]->student_name ?? 'Unnamed').'||'.$pair[1]->sahodaya_id)
            ->map(function ($pairs) {
                $reg = $pairs->first()[1];
                $participant = $pairs->first()[0];

                return [
                    $participant->student_name ?? '—',
                    $participant->class_name ?? '',
                    $reg->sahodaya_name ?: $reg->sahodaya_id,
                    $reg->school_name ?: $reg->school_id,
                    $pairs->count(),
                    $pairs->map(fn ($p) => $p[1]->item_code)->filter()->unique()->implode(', '),
                ];
            })->sortBy(fn ($r) => [$r[2], $r[3], $r[0]])->values()->all();

        return [
            'title' => 'Student-wise Participation Report',
            'headers' => ['Participant', 'Class', 'Sahodaya', 'School', 'Items', 'Item codes'],
            'rows' => $rows,
        ];
    }

    private function uniqueParticipants(StateFestEvent $event, array $filters): array
    {
        $rows = $this->registrationsQuery($event, $filters)->with('participants')->get()
            ->groupBy('sahodaya_id')
            ->map(function ($group) {
                $names = $group->flatMap(fn ($r) => ($r->participants ?? collect())->pluck('student_name'))->filter();

                return [
                    $group->first()->sahodaya_name ?: ($group->first()->sahodaya_id ?: 'Unattributed'),
                    $names->unique()->count(),
                    $group->count(),
                    // The gap between the two is participants entered for more than one item.
                    max($group->count() - $names->unique()->count(), 0),
                ];
            })->sortBy(fn ($r) => $r[0])->values()->all();

        return [
            'title' => 'Unique Participant Counts',
            'headers' => ['Sahodaya', 'Unique participants', 'Total entries', 'Multi-item entries'],
            'rows' => $rows,
        ];
    }

    private function slotUsage(StateFestEvent $event, array $filters): array
    {
        $program = FestStateProgram::findOrFail($event->state_program_id);
        $matrix = app(StateSlotService::class)->matrix($program);

        $rows = [];
        foreach ($matrix['items'] as $item) {
            if (($filters['item_id'] ?? null) && $item['item_id'] !== $filters['item_id']) {
                continue;
            }
            foreach ($item['cells'] as $cell) {
                if (($filters['sahodaya_id'] ?? null) && $cell['sahodaya_id'] !== $filters['sahodaya_id']) {
                    continue;
                }
                $rows[] = [
                    $item['item_code'], $item['title'], $cell['sahodaya_name'],
                    $cell['slots'] ?? 'Unlimited', $cell['used'], $cell['available'] ?? '—',
                    $cell['exceeded'] ? 'OVER' : '',
                ];
            }
        }

        return [
            'title' => 'Sahodaya Slot Usage',
            'headers' => ['Code', 'Item', 'Sahodaya', 'Slots', 'Used', 'Available', 'Breach'],
            'rows' => $rows,
        ];
    }

    private function slotViolations(StateFestEvent $event): array
    {
        $all = $this->slotUsage($event, []);

        return [
            'title' => 'Slot Violations',
            'headers' => $all['headers'],
            'rows' => array_values(array_filter($all['rows'], fn ($r) => $r[6] === 'OVER')),
        ];
    }

    private function pendingApprovals(StateFestEvent $event, array $filters): array
    {
        $intakes = StateQualifierIntake::where('state_program_id', $event->state_program_id)
            ->when($filters['sahodaya_id'] ?? null, fn ($q, $id) => $q->where('sahodaya_id', $id))
            ->pluck('sahodaya_name', 'id');

        $rows = StateQualifierEntry::whereIn('intake_id', $intakes->keys())
            ->where('status', 'pending')
            ->orderBy('item_code')
            ->get()
            ->map(fn (StateQualifierEntry $e) => [
                $intakes[$e->intake_id] ?? '—',
                $e->school_name ?: $e->school_id,
                $e->item_code,
                $e->student_name,
                $e->class_name,
            ])->all();

        return [
            'title' => 'Pending Approval Register',
            'headers' => ['Sahodaya', 'School', 'Item', 'Participant', 'Class'],
            'rows' => $rows,
        ];
    }

    private function attendanceStatus(StateFestEvent $event, array $filters): array
    {
        $marked = StateAttendance::where('state_event_id', $event->id)->get()->keyBy('registration_id');

        $rows = $this->registrationsQuery($event, $filters)->where('status', 'approved')
            ->orderBy('item_code')->get()
            ->map(function (StateFestRegistration $r) use ($marked) {
                $attendance = $marked->get($r->id);

                return [
                    $r->item_code,
                    $this->participantNames($r),
                    $r->sahodaya_name ?: $r->sahodaya_id,
                    $r->school_name ?: $r->school_id,
                    $attendance?->status ?? 'not marked',
                ];
            })->all();

        return [
            'title' => 'Live Attendance Status',
            'headers' => ['Item', 'Participant(s)', 'Sahodaya', 'School', 'Attendance'],
            'rows' => $rows,
        ];
    }

    private function markEntryStatus(StateFestEvent $event, array $filters): array
    {
        $marks = StateFestMark::where('state_event_id', $event->id)->get()->groupBy('registration_id');

        $rows = $this->registrationsQuery($event, $filters)->where('status', 'approved')->get()
            ->groupBy('item_code')
            ->map(function ($group, $itemCode) use ($marks) {
                $entered = $group->filter(fn ($r) => $marks->has($r->id))->count();

                return [
                    $itemCode ?: '—',
                    $group->count(),
                    $entered,
                    $group->count() - $entered,
                    $entered === $group->count() ? 'complete' : 'pending',
                ];
            })->sortBy(fn ($r) => $r[0])->values()->all();

        return [
            'title' => 'Mark Entry Status',
            'headers' => ['Item', 'Registrations', 'Marks entered', 'Outstanding', 'Status'],
            'rows' => $rows,
        ];
    }

    private function results(): \App\Services\State\Fest\StateResultService
    {
        return app(\App\Services\State\Fest\StateResultService::class);
    }

    private function sahodayaRanking(StateFestEvent $event): array
    {
        return [
            'title' => 'Overall Sahodaya Ranking',
            'headers' => ['Rank', 'Sahodaya', 'District', 'Source', 'Points', '1st', '2nd', '3rd', 'Items'],
            'rows' => $this->results()->sahodayaStandings($event)->map(fn (array $r) => [
                $r['rank'], $r['sahodaya'], $r['district'] ?? '',
                $r['origin'] === 'external' ? 'Outside' : 'On platform',
                $r['points'], $r['firsts'], $r['seconds'], $r['thirds'], $r['items'],
            ])->all(),
        ];
    }

    /** Points per Sahodaya split by item category — the same totals, cut the other way. */
    private function categorySahodayaPoints(StateFestEvent $event): array
    {
        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->get()->keyBy('id');

        $registrations = StateFestRegistration::where('state_event_id', $event->id)->get()->keyBy('id');

        $rows = \App\Models\State\StateFestMark::where('state_event_id', $event->id)
            ->whereNotNull('position')->get()
            ->groupBy(function ($mark) use ($registrations, $items) {
                $registration = $registrations[$mark->registration_id] ?? null;
                $category = $items[$registration?->item_id ?? '']->class_group ?? 'Uncategorised';

                return ($registration->sahodaya_name ?? 'Unattributed').'||'.$category;
            })
            ->map(function ($group, $key) {
                [$sahodaya, $category] = explode('||', $key);

                return [$sahodaya, $category, (int) $group->sum('points'), $group->count()];
            })
            ->sortBy(fn ($r) => [$r[0], $r[1]])->values()->all();

        return [
            'title' => 'Category-wise Sahodaya Points',
            'headers' => ['Sahodaya', 'Category', 'Points', 'Entries'],
            'rows' => $rows,
        ];
    }

    private function schoolContribution(StateFestEvent $event, array $filters): array
    {
        $sahodayas = $filters['sahodaya_id'] ?? null
            ? StateSahodaya::where('id', $filters['sahodaya_id'])->get()
            : StateSahodaya::query()->forState($event->state_id)->get();

        $rows = [];

        foreach ($sahodayas as $sahodaya) {
            foreach ($this->results()->schoolContribution($event, $sahodaya->id) as $row) {
                $rows[] = [$sahodaya->name, $row['school'], $row['points'], $row['firsts'], $row['entries']];
            }
        }

        return [
            'title' => 'School Contribution to Sahodaya Points',
            'headers' => ['Sahodaya', 'School', 'Points', '1st', 'Entries'],
            'rows' => $rows,
        ];
    }

    private function itemWiseResults(StateFestEvent $event, array $filters): array
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->when($filters['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->with('participants')->get()->keyBy('id');

        // Only published items: a provisional ranking is the office's working view.
        $published = \App\Models\State\StateItemResult::where('state_event_id', $event->id)
            ->whereIn('status', ['published', 'locked'])->pluck('item_id');

        $rows = \App\Models\State\StateFestMark::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->keys())
            ->whereNotNull('position')->get()
            ->filter(fn ($m) => $published->contains($registrations[$m->registration_id]->item_id ?? null))
            ->sortBy([fn ($a, $b) => strcmp($a->item_code ?? '', $b->item_code ?? ''), fn ($a, $b) => $a->position <=> $b->position])
            ->map(function ($mark) use ($registrations) {
                $registration = $registrations[$mark->registration_id];
                $participant = $registration->participants->firstWhere('id', $mark->participant_id);

                return [
                    $registration->item_code, $mark->position,
                    $participant->student_name ?? '—',
                    $registration->sahodaya_name, $registration->school_name,
                    $mark->grade, $mark->score, $mark->points,
                ];
            })->values()->all();

        return [
            'title' => 'Item-wise Top Results',
            'headers' => ['Item', 'Position', 'Participant', 'Sahodaya', 'School', 'Grade', 'Score', 'Points'],
            'rows' => $rows,
        ];
    }

    private function individualChampionship(StateFestEvent $event): array
    {
        return [
            'title' => 'Individual Championship',
            'headers' => ['Participant', 'Class', 'Sahodaya', 'School', 'Points', '1st', 'Items'],
            'rows' => $this->results()->individualChampionship($event)->map(fn (array $r) => [
                $r['participant'], $r['class_name'] ?? '', $r['sahodaya'], $r['school'],
                $r['points'], $r['firsts'], $r['items'],
            ])->all(),
        ];
    }

    private function itemSchedule(StateFestEvent $event): array
    {
        $rows = app(\App\Services\State\Fest\StateScheduleService::class)->scheduleFor($event)
            ->filter(fn (array $r) => $r['is_scheduled'])
            ->map(fn (array $r) => [
                $r['item_code'], $r['title'], $r['scheduled_on'],
                $r['reporting_at'] ?? '—', $r['starts_at'] ?? '—', $r['ends_at'] ?? '—',
                $r['venue_name'] ?? '—', $r['participants'],
            ])->values()->all();

        return [
            'title' => 'Item Venue & Time Schedule',
            'headers' => ['Code', 'Item', 'Date', 'Report', 'Start', 'Finish', 'Stage', 'Entries'],
            'rows' => $rows,
        ];
    }

    private function scheduleClashes(StateFestEvent $event): array
    {
        $clashes = app(\App\Services\State\Fest\StateScheduleService::class)->clashes($event);
        $rows = [];

        foreach ($clashes['participant'] as $c) {
            $rows[] = [ucfirst($c['kind']), $c['name'], $c['sahodaya'], $c['school'], $c['date'], $c['a'], $c['b']];
        }
        foreach ($clashes['venue'] as $c) {
            $rows[] = ['Stage', $c['venue'], '—', '—', $c['date'], $c['a'], $c['b']];
        }
        foreach ($clashes['sahodaya'] as $c) {
            $rows[] = ['Sahodaya load', '—', $c['sahodaya'], '—', $c['date'], $c['a'], $c['b']];
        }

        return [
            'title' => 'Schedule Clash Report',
            'headers' => ['Kind', 'Who', 'Sahodaya', 'School', 'Date', 'Item A', 'Item B'],
            'rows' => $rows,
        ];
    }

    private function feeSummary(StateFestEvent $event): array
    {
        $directory = StateSahodaya::query()->forState($event->state_id)->orderBy('name')->get();

        // Remittances key on the same source keys the intakes do, so a promoted Sahodaya's demand
        // may sit under either — look both up rather than showing it as unbilled.
        $rows = $directory->map(function (StateSahodaya $sahodaya) {
            $remittance = StateRemittance::whereIn('sahodaya_id', $sahodaya->sourceKeys() ?: ['__none__'])->first();

            return [
                $sahodaya->name,
                $sahodaya->district ?? '',
                $remittance?->amount ?? 0,
                $remittance?->status ?? 'no demand raised',
            ];
        })->all();

        return [
            'title' => 'Sahodaya Fee Summary',
            'headers' => ['Sahodaya', 'District', 'Amount', 'Status'],
            'rows' => $rows,
        ];
    }

    private function participantNames(StateFestRegistration $registration): string
    {
        $participants = $registration->relationLoaded('participants')
            ? $registration->participants
            : $registration->participants()->get();

        return $participants->pluck('student_name')->filter()->implode(', ') ?: '—';
    }
}
