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
    public function assertEligible(\App\Models\FestEvent $event, FestEventItem $item, Student $student): void
    {
        abort_unless(Tenant::whereKey($student->tenant_id)->where('parent_id', $event->tenant_id)->where('type', 'school')->exists(), 403);
        $errors = app(FestRegistrationEligibilityService::class)->validateWildcardStudent($student, $event, $item);
        if (FestHeadItemNavigationService::filterToOwnPhase(collect([$item->loadMissing('phase')]), $event)->isEmpty()) {
            $errors[] = 'Item does not belong to this competition phase.';
        }
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['item_id' => implode(' ', $errors)]);
        }
    }

    public function hasActiveRequest(\App\Models\FestEvent $event, Student $student, FestEventItem $item): bool
    {
        return FestAppeal::where('event_id', $event->id)->where('student_id', $student->id)
            ->where('item_id', $item->id)->whereIn('appeal_type', FestAppeal::WILDCARD_TYPES)
            ->where(function ($query) {
                $query->where('status', 'pending')->orWhere(function ($approved) {
                    $approved->where('status', 'approved')->where(function ($active) {
                        $active->where('appeal_type', '!=', FestAppeal::TYPE_SAHODAYA_WILDCARD)
                            ->orWhereNull('granted_registration_id')
                            ->orWhereHas('grantedRegistration', fn ($registration) => $registration->active());
                    });
                });
            })->exists();
    }

    public function correctItem(FestAppeal $appeal, FestEventItem $item, string $reason): FestAppeal
    {
        return DB::transaction(function () use ($appeal, $item, $reason) {
            $student = Student::whereKey($appeal->student_id)->lockForUpdate()->firstOrFail();
            $appeal = FestAppeal::whereKey($appeal->id)->lockForUpdate()->firstOrFail();
            abort_unless($appeal->isWildcard() && in_array($appeal->status, ['pending', 'approved'], true), 422, 'Only active wildcard appeals can be corrected.');
            abort_if($appeal->granted_state_reference, 422, 'This entry has already been sent to State. Correct it through State scrutiny.');
            abort_if($appeal->status === 'approved' && $appeal->appeal_type === FestAppeal::TYPE_SAHODAYA_WILDCARD
                && ! $appeal->granted_registration_id, 422, 'This approved appeal has no linked registration. Review it before correcting its item.');
            $this->assertEligible($appeal->event, $item, $student);
            abort_if($appeal->appeal_type === FestAppeal::TYPE_STATE_WILDCARD && ! $item->state_program_item_id, 422, 'Choose an item from the State catalog.');
            abort_if($appeal->item_id === $item->id, 422, 'Choose a different item.');
            abort_if($this->hasActiveRequest($appeal->event, $student, $item), 422, 'An active wildcard appeal already exists for the selected item.');
            $active = FestParticipant::where('student_id', $student->id)->whereHas('registration', fn ($query) => $query
                ->where('event_id', $appeal->event_id)->where('item_id', $item->id)->active())->exists();
            abort_if($active, 422, 'Student already has an active entry for the selected item.');
            if ($registration = $appeal->grantedRegistration) {
                abort_unless(in_array($registration->status, FestRegistration::ACTIVE_STATUSES, true), 422, 'This entry was withdrawn. Create a new wildcard for the correct item.');
                abort_if($registration->item?->results_published_at || $item->results_published_at, 422, 'Cannot move an entry after item results are published.');
                $ids = $registration->participants()->pluck('id');
                abort_if(\App\Models\FestMark::whereIn('participant_id', $ids)->exists(), 422, 'Marks already exist. The item cannot be changed.');
                abort_if(\App\Models\FestJudgeScore::whereIn('participant_id', $ids)->exists()
                    || \App\Models\FestMarkJudgeScore::whereIn('participant_id', $ids)->exists(), 422, 'Judge scores already exist. The item cannot be changed.');
                abort_if(\App\Models\FestSchedule::whereIn('participant_id', $ids)->exists(), 422, 'Remove the participant performance schedule before changing the item.');
                abort_if($registration->item?->participant_type !== $item->participant_type, 422, 'Choose an item of the same participant type.');
                $registration->update(['item_id' => $item->id]);
                foreach ($registration->participants()->get() as $participant) {
                    $participant->update(['chest_no' => null, 'chest_head_id' => 0, 'chest_revealed_at' => null,
                        'order_no' => null, 'item_registration_number' => null]);
                    app(FestNumberingService::class)->assignParticipantNumbers($participant->fresh());
                }
            }
            $appeal->update(['item_id' => $item->id, 'resolution_note' => trim(($appeal->resolution_note ?? '')."\nItem corrected: {$reason}")]);

            return $appeal->fresh();
        });
    }

    public function eligibleItems(\App\Models\FestEvent $event, Student $student, string $type): array
    {
        $student->loadMissing('schoolClass.classCategory');
        $labels = \App\Support\FestClassGroupScheme::labels(null, $event->rootEvent());
        return FestHeadItemNavigationService::filterToOwnPhase(
            FestEventItem::where('event_id', $event->id)->where('is_enabled', true)
                ->when($type === FestAppeal::TYPE_STATE_WILDCARD, fn ($query) => $query->whereNotNull('state_program_item_id'))
                ->with('phase')->orderBy('display_order')->orderBy('title')->get(), $event
        )->filter(fn ($item) => app(FestRegistrationEligibilityService::class)->validateWildcardStudent($student, $event, $item) === [])
            ->map(fn ($item) => ['id' => $item->id, 'title' => $item->title, 'item_code' => $item->item_code,
                'category_label' => \App\Support\FestItemCategoryLabel::resolve($item, $labels),
                'type_label' => \App\Support\FestItemCategoryLabel::typeLabel($item->participant_type),
                'gender_label' => \App\Support\FestItemCategoryLabel::genderLabel($item->gender),
            ])->values()->all();
    }

    /**
     * Single entry point both appeal-review controllers call instead of updating
     * the FestAppeal row directly, so the status flip and the wildcard grant it
     * triggers happen together (and never happen without each other).
     */
    public function resolve(FestAppeal $appeal, string $status, ?string $resolutionNote, int $resolvedByUserId): FestAppeal
    {
        return DB::transaction(function () use ($appeal, $status, $resolutionNote, $resolvedByUserId) {
            if ($appeal->isWildcard() && $appeal->student_id) {
                Student::whereKey($appeal->student_id)->lockForUpdate()->firstOrFail();
            }
            $appeal = FestAppeal::whereKey($appeal->id)->lockForUpdate()->firstOrFail();
            abort_if($appeal->status !== 'pending', 422, 'Only pending appeals can be resolved.');
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
        $this->assertEligible($event, $item, $student);
        $sahodayaId = $event->tenant_id;
        $placeholder = $this->appealPoolSchool($sahodayaId);

        return DB::transaction(function () use ($appeal, $item, $student, $event, $placeholder) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $appeal->refresh();
            abort_if($appeal->granted_registration_id, 422, 'This appeal has already been granted a slot.');
            $active = FestParticipant::where('student_id', $student->id)
                ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id)->where('item_id', $item->id)->active())->exists();
            abort_if($active, 422, 'Student already has an active entry for this item. A withdrawn entry does not block a new wildcard.');

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
        $this->assertEligible($event, $item, $student);
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
                'class_name'             => $student->schoolClass?->name,
                'gender'                 => $student->gender,
                'source_student_id'      => (string) $student->id,
                'roll_number'            => $student->roll_number,
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

    private function appealPoolSchool(string $sahodayaId): Tenant
    {
        return Tenant::ensureAppealPoolSchool($sahodayaId);
    }
}
