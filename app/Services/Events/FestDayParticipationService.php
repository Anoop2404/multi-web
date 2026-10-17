<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateItemSchedule;

class FestDayParticipationService
{
    public function sahodaya(FestEvent $event): array
    {
        $items = FestHeadItemNavigationService::filterToOwnPhase(
            FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->with('phase')->get(), $event
        )->pluck('id');
        $schedules = FestSchedule::where('event_id', $event->id)->whereIn('item_id', $items)
            ->whereNotNull('scheduled_at')->with('participant:id,group_id')->get();
        $itemSlots = $schedules->whereNull('participant_id')->keyBy('item_id');
        $personSlots = $schedules->whereNotNull('participant_id')->keyBy('participant_id');
        $groupedSlots = $schedules->toBase()->filter(fn ($slot) => $slot->participant?->group_id)
            ->groupBy(fn ($slot) => $slot->item_id.':'.$slot->participant->group_id);
        $conflictingGroups = $groupedSlots->filter(fn ($slots) => $slots
            ->map(fn ($slot) => $slot->scheduled_at->format('Y-m-d'))->unique()->count() > 1);
        $groupSlots = $groupedSlots->except($conflictingGroups->keys()->all())->map->first();
        $participants = FestParticipant::whereHas('registration', fn ($query) => $query
            ->where('event_id', $event->id)->whereIn('item_id', $items)->active())
            ->where(fn ($query) => $query->whereNull('participant_role')->orWhere('participant_role', '!=', 'standby'))
            ->whereNull('disqualified_at')
            ->with(['registration.school', 'student.schoolClass', 'teacher'])->get();
        $records = $participants->map(function ($participant) use ($personSlots, $groupSlots, $itemSlots) {
            $registration = $participant->registration;
            $slot = $personSlots->get($participant->id)
                ?? ($participant->group_id ? $groupSlots->get($registration->item_id.':'.$participant->group_id) : null)
                ?? $itemSlots->get($registration->item_id);

            return [
                'date' => $slot?->scheduled_at?->format('Y-m-d'),
                'person' => $participant->student_id ? 'student:'.$participant->student_id
                    : ($participant->teacher_id ? 'teacher:'.$participant->teacher_id : 'entry:'.$participant->id),
                'identified' => (bool) ($participant->student_id || $participant->teacher_id),
                'entry' => $participant->id, 'item' => $registration->item_id,
                'school' => $registration->school_id, 'school_name' => $registration->school?->name ?? 'Unknown school',
                'name' => $participant->student?->name ?? $participant->teacher?->name ?? $participant->display_name ?? 'Participant',
                'class_name' => $participant->student?->schoolClass?->name,
                'identifier' => $participant->student?->reg_no ?: $participant->student?->roll_number,
            ];
        })->all();

        $summary = $this->summarize($records, $schedules->map(fn ($slot) => $slot->scheduled_at->format('Y-m-d'))->all(), 'Active registrations');
        $summary['warnings'] = $conflictingGroups->isEmpty() ? [] : [
            $conflictingGroups->count().' team(s) have member schedules on different days. Explicit participant dates are used; other members use the item date or remain unscheduled.',
        ];

        return $summary;
    }

    public function state(StateFestEvent $event): array
    {
        $slots = StateItemSchedule::where('state_event_id', $event->id)->whereNotNull('scheduled_on')->get()->keyBy('item_id');
        $registrations = StateFestRegistration::where('state_event_id', $event->id)->where('status', 'approved')
            ->with('participants')->get();
        // Reconcile legacy roll-only entries only when the school roll maps to one source ID.
        // Ambiguous rolls remain separate; matching names is not proof of identity.
        $aliases = [];
        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participant) {
                $id = trim((string) ($participant->meta['source_student_id'] ?? ''));
                $roll = trim((string) $participant->roll_number);
                if ($id !== '' && $roll !== '' && filled($registration->school_id)) {
                    $key = json_encode([$registration->sahodaya_id, $registration->school_id, $roll]);
                    $aliases[$key][$id] = true;
                }
            }
        }
        $records = [];
        foreach ($registrations as $registration) {
            foreach ($registration->participants->filter(fn ($participant) => $participant->isCompeting()) as $participant) {
                $sourceId = trim((string) ($participant->meta['source_student_id'] ?? ''));
                $roll = trim((string) $participant->roll_number);
                $alias = $aliases[json_encode([$registration->sahodaya_id, $registration->school_id, $roll])] ?? [];
                if ($sourceId === '' && count($alias) === 1) {
                    $sourceId = (string) array_key_first($alias);
                }
                $identity = $sourceId !== '' ? 'student:'.$sourceId : ($roll !== '' ? 'roll:'.$roll : null);
                if (blank($registration->school_id) || ($sourceId === '' && count($alias) > 1)) {
                    $identity = null;
                }
                $schoolKey = json_encode([$registration->sahodaya_id,
                    filled($registration->school_id) ? $registration->school_id : 'unverified:'.$registration->school_name]);
                $records[] = [
                    'date' => $slots->get($registration->item_id)?->scheduled_on?->format('Y-m-d'),
                    'person' => $identity ? $schoolKey.':'.$identity : 'entry:'.$participant->id,
                    'identified' => $identity !== null, 'entry' => $participant->id, 'item' => $registration->item_id,
                    'school' => $schoolKey, 'school_name' => $registration->school_name ?: 'Unknown school',
                    'name' => $participant->student_name, 'class_name' => $participant->class_name,
                    'identifier' => $roll !== '' ? $roll : ($sourceId !== '' ? $sourceId : null),
                ];
            }
        }

        return $this->summarize($records, $slots->map(fn ($slot) => $slot->scheduled_on->format('Y-m-d'))->all(), 'Approved registrations');
    }

    private function summarize(array $records, array $dates, string $basis): array
    {
        $records = collect($records);
        $scheduled = $records->filter(fn ($row) => $row['date'] !== null);
        $days = collect($dates)->merge($scheduled->pluck('date'))->unique()->sort()->values()
            ->map(fn ($date) => ['date' => $date] + $this->counts($scheduled->where('date', $date)->all()))->all();

        return [
            'days' => $days,
            'overall' => $this->counts($scheduled->all()),
            'event_total' => $this->counts($records->all()),
            'unscheduled' => $this->counts($records->whereNull('date')->all()),
            'basis' => $basis,
            'warnings' => [],
        ];
    }

    private function counts(array $records): array
    {
        $records = collect($records);
        $people = $records->unique('person');
        $schools = $people->groupBy('school')->map(fn ($members, $key) => [
            'key' => $key, 'name' => $members->first()['school_name'], 'count' => $members->count(),
            'students' => $members->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->map(fn ($row) => [
                'key' => $row['person'], 'name' => $row['name'], 'class_name' => $row['class_name'],
                'identified' => $row['identified'], 'identifier' => $row['identifier'] ?? null,
            ])->values()->all(),
        ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return [
            'unique_participants' => $people->count(), 'participant_entries' => $records->unique('entry')->count(),
            'items' => $records->pluck('item')->unique()->count(), 'school_count' => count($schools),
            'unidentified_participants' => $people->where('identified', false)->count(), 'schools' => $schools,
        ];
    }
}
