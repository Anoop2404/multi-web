<?php

namespace App\Services\Events;

use App\Models\FestAppeal;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestStateProgram;
use App\Models\FestStateSubmissionOutbox;
use App\Models\StateDomain;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\State\StateSubmissionClient;
use Illuminate\Support\Facades\DB;

/**
 * Wildcard-appeal effects: a student skipped at one tier (not selected by their
 * school, or not placed at Sahodaya level) can appeal for a slot at the next
 * tier up. Approving a dispute-type FestAppeal only ever flips its own status
 * row (see FestAppealController::resolve) — this service is what actually grants
 * the slot for the two wildcard types, on top of that same status flip.
 */
class FestAppealWildcardService
{
    /**
     * Single entry point both appeal-review controllers call instead of updating
     * the FestAppeal row directly, so the status flip and the wildcard grant it
     * triggers happen together (and never happen without each other).
     */
    public function resolve(FestAppeal $appeal, string $status, ?string $resolutionNote, int $resolvedByUserId): FestAppeal
    {
        return DB::transaction(function () use ($appeal, $status, $resolutionNote, $resolvedByUserId) {
            $appeal->update([
                'status'              => $status,
                'resolution_note'     => $resolutionNote,
                'resolved_by_user_id' => $resolvedByUserId,
                'resolved_at'         => now(),
            ]);

            if ($status === 'approved') {
                match ($appeal->appeal_type) {
                    FestAppeal::TYPE_SAHODAYA_WILDCARD => $this->grantSahodayaSlot($appeal),
                    FestAppeal::TYPE_STATE_WILDCARD => $this->grantStateSlot($appeal, $resolvedByUserId),
                    default => null,
                };
            }

            return $appeal->fresh();
        });
    }

    /**
     * School-level appeal: the student's own school didn't select them, so they
     * compete at the Sahodaya-level item under the Sahodaya's placeholder "Appeal
     * School" tenant instead of a real one. Championship totals (EventContext,
     * FestCumulativeChampionshipService) exclude that placeholder's school_id, so
     * the result never inflates the real origin school's points — but
     * origin_school_id is kept on the registration so certificates still show the
     * student's real school.
     */
    public function grantSahodayaSlot(FestAppeal $appeal): FestRegistration
    {
        abort_if($appeal->appeal_type !== FestAppeal::TYPE_SAHODAYA_WILDCARD, 422, 'Not a Sahodaya-level wildcard appeal.');
        abort_if($appeal->granted_registration_id, 422, 'This appeal has already been granted a slot.');

        $item = FestEventItem::findOrFail($appeal->item_id);
        $student = Student::findOrFail($appeal->student_id);
        abort_if($item->event_id !== $appeal->event_id, 422, 'Item does not belong to this appeal\'s event.');

        $event = $appeal->event;
        $sahodayaId = $event->tenant_id;
        $placeholder = $this->appealPoolSchool($sahodayaId);

        return DB::transaction(function () use ($appeal, $item, $student, $event, $placeholder) {
            $registration = FestRegistration::create([
                'event_id'         => $event->id,
                'item_id'          => $item->id,
                'school_id'        => $placeholder->id,
                'origin_school_id' => $student->tenant_id,
                'status'           => 'approved',
                'submitted_at'     => now(),
            ]);

            $participant = FestParticipant::create([
                'registration_id'  => $registration->id,
                'student_id'       => $student->id,
                'event_id'         => $event->id,
                'participant_type' => 'student',
                'participant_role' => 'performer',
            ]);

            app(FestNumberingService::class)->assignParticipantNumbers($participant);

            $appeal->update(['granted_registration_id' => $registration->id]);

            return $registration->fresh(['participants.student', 'item']);
        });
    }

    /**
     * Sahodaya-level appeal: the student didn't place/qualify at the Sahodaya
     * event, so they get a wildcard slot at the State-level item instead. Unlike
     * the Sahodaya-level case, the real origin school IS credited (the school did
     * legitimately field this student — they just didn't win at Sahodaya level),
     * while the Sahodaya itself gets no credit, since state-level points never
     * feed back into any Sahodaya aggregate in the first place (confirmed: no
     * such aggregate exists anywhere in the codebase).
     *
     * State runs as a separate domain reached over HTTP (see StateSubmissionClient) —
     * there is no local DB connection to write a StateQualifierEntry into directly.
     * This reuses the exact same outbox+HTTP submission path a normal qualifier
     * batch takes (FestStateQualifierPayloadBuilder::enqueue()/StateSubmissionClient),
     * just with a single hand-built entry instead of one derived from published marks,
     * so it lands in the same pending review queue State admins already use.
     */
    public function grantStateSlot(FestAppeal $appeal, ?int $submittedByUserId = null): FestStateSubmissionOutbox
    {
        abort_if($appeal->appeal_type !== FestAppeal::TYPE_STATE_WILDCARD, 422, 'Not a State-level wildcard appeal.');
        abort_if($appeal->granted_state_reference, 422, 'This appeal has already been submitted to State.');

        $event = $appeal->event;
        abort_if(! $event->state_program_id, 422, 'Event is not linked to a state program.');

        $program = FestStateProgram::findOrFail($event->state_program_id);
        abort_if(! $program->state_domain_id, 422, 'State program has no state domain configured.');
        $domain = StateDomain::findOrFail($program->state_domain_id);

        $item = FestEventItem::findOrFail($appeal->item_id);
        abort_if(! $item->state_program_item_id, 422, 'This item is not part of the state catalog.');

        $student = Student::findOrFail($appeal->student_id);
        $originSchool = Tenant::findOrFail($student->tenant_id);

        $payload = [
            'state_program_id' => $program->id,
            'source_tenant_id' => $event->tenant_id,
            'source_event_id'  => $event->id,
            'submitted_at'     => now()->toIso8601String(),
            'entries'          => [[
                'source_registration_id' => null,
                'source_participant_id'  => null,
                'school_id'              => $originSchool->id,
                'school_name'            => $originSchool->name,
                'item_id'                => $item->state_program_item_id,
                'item_code'              => $item->item_code,
                'item_name'              => $item->title,
                'student_name'           => $student->name,
                'class_name'             => $student->class_name ?? null,
                'position'               => null,
                'grade'                  => null,
                'points'                 => 0,
                'partition_key'          => null,
                'qualifier_type'         => 'wildcard_appeal',
                'participant_type'       => $item->participant_type,
                'participants'           => [],
            ]],
        ];

        $hash = hash('sha256', json_encode($payload['entries'], JSON_THROW_ON_ERROR));
        $idempotencyKey = "wildcard-appeal:{$appeal->id}:{$hash}";

        $outbox = FestStateSubmissionOutbox::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'state_program_id' => $program->id,
                'source_event_id'  => $event->id,
                'submission_type'  => 'qualifier_wildcard',
                'payload'          => $payload,
                'payload_hash'     => $hash,
                'status'           => 'pending',
                'submitted_by'     => $submittedByUserId,
            ]
        );

        app(StateSubmissionClient::class)->send($outbox, $domain);

        $appeal->update(['granted_state_reference' => $outbox->id]);

        return $outbox->fresh();
    }

    /**
     * Find-or-create the one placeholder "Appeal School" tenant this Sahodaya
     * files wildcard registrations under. A school-type tenant whose parent is a
     * Sahodaya shares that Sahodaya's own database (TenantObserver::creating()) —
     * no new database is provisioned, so this is cheap and side-effect-free.
     */
    private function appealPoolSchool(string $sahodayaId): Tenant
    {
        $id = "appeal-school-{$sahodayaId}";

        $existing = Tenant::find($id);
        if ($existing) {
            return $existing;
        }

        try {
            return Tenant::create([
                'id'             => $id,
                'type'           => 'school',
                'name'           => 'Appeal School',
                'parent_id'      => $sahodayaId,
                'is_active'      => true,
                'is_appeal_pool' => true,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Lost a create race against a concurrent appeal approval for the same
            // Sahodaya — the row now exists, so just return it.
            return Tenant::findOrFail($id);
        }
    }
}
