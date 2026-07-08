<?php

namespace App\Services\State;

use App\Models\FestStateProgram;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use Illuminate\Support\Facades\DB;

class StateQualifierMaterializationService
{
    /** @return array{event: StateFestEvent, registrations: int, participants: int} */
    public function materializeApprovedIntake(StateQualifierIntake $intake): array
    {
        return DB::transaction(function () use ($intake) {
            $event = $this->stateEventFor($intake);
            $registrations = 0;
            $participants = 0;

            StateQualifierEntry::where('intake_id', $intake->id)
                ->where('status', 'approved')
                ->orderBy('item_code')
                ->chunkById(100, function ($entries) use ($event, &$registrations, &$participants) {
                    foreach ($entries as $entry) {
                        $registration = StateFestRegistration::updateOrCreate(
                            [
                                'state_event_id' => $event->id,
                                'qualifier_entry_id' => $entry->id,
                            ],
                            [
                                'school_id' => $entry->school_id,
                                'school_name' => $entry->school_name,
                                'item_id' => $entry->item_id,
                                'item_code' => $entry->item_code,
                                'status' => 'approved',
                                'meta' => array_merge($entry->meta ?? [], [
                                    'intake_id' => $entry->intake_id,
                                    'qualifier_type' => $entry->qualifier_type,
                                    'source_registration_id' => $entry->source_registration_id,
                                    'source_participant_id' => $entry->source_participant_id,
                                ]),
                            ],
                        );

                        $participant = StateFestParticipant::updateOrCreate(
                            ['registration_id' => $registration->id],
                            [
                                'student_name' => $entry->student_name,
                                'class_name' => $entry->class_name,
                                'meta' => [
                                    'source_participant_id' => $entry->source_participant_id,
                                    'position' => $entry->position,
                                    'grade' => $entry->grade,
                                    'points' => $entry->points,
                                    'partition_key' => $entry->partition_key,
                                ],
                            ],
                        );

                        if ($registration->wasRecentlyCreated) {
                            $registrations++;
                        }
                        if ($participant->wasRecentlyCreated) {
                            $participants++;
                        }
                    }
                });

            return compact('event', 'registrations', 'participants');
        });
    }

    private function stateEventFor(StateQualifierIntake $intake): StateFestEvent
    {
        $program = FestStateProgram::find($intake->state_program_id);

        return StateFestEvent::firstOrCreate(
            ['state_program_id' => $intake->state_program_id],
            [
                'name' => $program?->title ? "{$program->title} - State Finals" : "State Finals {$intake->state_program_id}",
                'status' => 'draft',
                'starts_on' => $program?->event_start,
                'ends_on' => $program?->event_end,
                'settings' => [
                    'source' => 'qualifier_intake',
                    'state_program_title' => $program?->title,
                ],
            ],
        );
    }
}
