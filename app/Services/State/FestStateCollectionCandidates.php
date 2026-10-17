<?php

namespace App\Services\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\Tenant;
use App\Services\Events\FestHeadItemNavigationService;
use App\Services\Events\FestPartitionService;
use App\Support\FestTeamSquadRules;
use Illuminate\Support\Collection;

/** School confirmation candidates; never manufactures a Sahodaya rank or result. */
class FestStateCollectionCandidates
{
    public function pool(FestStateProgram $program, FestEvent $hub): Collection
    {
        $nominations = app(FestStateNominationService::class);
        $events = collect($nominations->sourceEvents($hub, app(FestPartitionService::class)));
        $stateItems = FestStateProgramItem::where('state_program_id', $program->id)->get()->keyBy('id');
        $items = $events->flatMap(fn ($event) => FestHeadItemNavigationService::filterToOwnPhase(
            FestEventItem::where('event_id', $event->id)->where('is_enabled', true)->whereNotNull('state_program_item_id')->with('phase')->get(), $event
        ))->filter(fn ($item) => isset($stateItems[$item->state_program_item_id])
            && (int) ($item->qualify_count ?? $stateItems[$item->state_program_item_id]->qualify_count) > 0)->keyBy('id');
        // Tenant records live centrally. A whereHas('school') here would embed a
        // tenants subquery in the isolated Sahodaya database, where it cannot run.
        $schoolIds = Tenant::where('type', 'school')->where('parent_id', $hub->tenant_id)->pluck('id');
        $participants = FestParticipant::whereHas('registration', fn ($q) => $q->whereIn('item_id', $items->keys())
            ->whereIn('school_id', $schoolIds)->active())
            ->whereNull('disqualified_at')->where(fn ($q) => $q->whereNull('participant_role')->orWhere('participant_role', 'performer'))
            ->with(['registration.school', 'student.schoolClass', 'group'])->get()->keyBy('id');
        $entryKey = function ($participant) use ($items) {
            $item = $items[$participant->registration->item_id];
            return FestTeamSquadRules::isMultiPerson($item->participant_type)
                ? ($participant->group_id ? 'group:'.$participant->group_id : 'registration:'.$participant->registration_id)
                : 'participant:'.$participant->id;
        };
        $published = fn ($item) => ! $item->results_hidden
            && ($item->results_published_at || $events->firstWhere('id', $item->event_id)?->results_published);
        $ranked = \App\Models\FestMark::whereIn('item_id', $items->keys())->whereBetween('position', [1, 5])
            ->orderBy('position')->orderByDesc('score')->get()
            ->filter(function ($mark) use ($participants, $items, $published) {
                $person = $participants->get($mark->participant_id);
                return $person && $person->registration->item_id === $mark->item_id
                    && $published($items[$mark->item_id]);
            })->unique(fn ($mark) => $entryKey($participants[$mark->participant_id]))
            ->map(function ($mark) use ($participants, $items, $events) {
                $person = $participants[$mark->participant_id];
                $registration = $person->registration;
                $item = $items[$registration->item_id];
                return [
                    'mark_id' => $mark->id, 'source_event_id' => $item->event_id,
                    'registration_id' => $registration->id, 'participant_id' => $person->id,
                    'item_id' => $item->state_program_item_id, 'item_code' => $item->item_code, 'item_title' => $item->title,
                    'school_id' => $registration->school_id, 'school_name' => $registration->school?->name,
                    'student_name' => $person->group?->team_name ?: ($person->student?->name ?? $person->display_name ?? 'Participant'),
                    'roll_number' => $person->student?->roll_number, 'class_name' => $person->student?->schoolClass?->name,
                    'source_position' => $mark->position, 'grade' => $mark->grade, 'score' => $mark->score,
                    'partition_key' => app(FestPartitionService::class)->partitionKey($events->firstWhere('id', $item->event_id)),
                    'is_eligible' => true, 'direct_entry' => false,
                ];
            });

        $direct = $participants->values()->filter(fn ($person) => ! $published($items[$person->registration->item_id]))
            ->unique($entryKey)->groupBy(fn ($person) => $items[$person->registration->item_id]->state_program_item_id)
            ->filter(fn ($entries) => $entries->count() <= 2)
            // A State item must not mix unpublished direct entries with published winners.
            ->reject(fn ($entries, $stateItemId) => $items->contains(fn ($item) => $item->state_program_item_id === $stateItemId && $published($item)))
            ->flatMap(fn ($entries) => $entries->map(function ($person) use ($items, $events) {
                $registration = $person->registration;
                $item = $items[$registration->item_id];
                return [
                    'mark_id' => null, 'source_event_id' => $item->event_id,
                    'registration_id' => $registration->id, 'participant_id' => $person->id,
                    'item_id' => $item->state_program_item_id, 'item_code' => $item->item_code, 'item_title' => $item->title,
                    'school_id' => $registration->school_id, 'school_name' => $registration->school?->name,
                    'student_name' => $person->group?->team_name ?: ($person->student?->name ?? $person->display_name ?? 'Participant'),
                    'roll_number' => $person->student?->roll_number, 'class_name' => $person->student?->schoolClass?->name,
                    'source_position' => null, 'grade' => null, 'score' => null,
                    'partition_key' => app(FestPartitionService::class)->partitionKey($events->firstWhere('id', $item->event_id)),
                    'is_eligible' => true, 'direct_entry' => true,
                ];
            }));

        return $ranked->concat($direct)->values();
    }

    public function quota(Collection $candidates, int $configured): int
    {
        return $candidates->contains('direct_entry', true) ? min($candidates->count(), min(2, $configured)) : min(2, $configured);
    }
}
