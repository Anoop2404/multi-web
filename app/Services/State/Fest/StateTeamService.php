<?php

namespace App\Services\State\Fest;

use App\Models\FestStateProgramItem;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateSubstitution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 4 of the State Kalotsav module — teams, squads and substitutions.
 *
 * A team entry is one registration with several participants; the slot it consumes belongs to the
 * entry, not the head count. Composition rules come from the item (min_group_size / max_group_size),
 * and standbys are excluded from the size check because they travel without competing.
 *
 * A substitution is deliberately not an edit. Replacing a participant after certification is a
 * decision with a reason, evidence, a deadline and an approver, and the record of it has to survive
 * the change — so the request is stored, decided, and only then applied.
 */
class StateTeamService
{
    public function __construct(private StateEventSettings $settings) {}

    /**
     * Teams for an event: registrations carrying more than one participant, plus any registration
     * for an item that is a group item even if it currently has one.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function teamsFor(StateFestEvent $event, ?string $sahodayaId = null)
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->when($sahodayaId, fn ($q) => $q->where('sahodaya_id', $sahodayaId))
            ->with('participants')
            ->get();

        $items = FestStateProgramItem::where('state_program_id', $event->state_program_id)
            ->get()->keyBy('id');

        return $registrations
            ->filter(function (StateFestRegistration $r) use ($items) {
                $item = $items->get($r->item_id);

                return $r->participants->count() > 1
                    || in_array($item?->participant_type, ['group', 'team', 'pair', 'trio'], true);
            })
            ->map(function (StateFestRegistration $r) use ($items) {
                $item = $items->get($r->item_id);
                $competing = $r->participants->filter(fn ($p) => $p->isCompeting());

                return [
                    'registration_id' => $r->id,
                    'sahodaya' => $r->sahodaya_name ?: $r->sahodaya_id,
                    'sahodaya_id' => $r->sahodaya_id,
                    // The School every member comes from — a team belongs to one School inside the
                    // Sahodaya, and the State never drops it.
                    'school' => $r->school_name ?: $r->school_id,
                    'item_code' => $r->item_code,
                    'item_name' => $item?->title,
                    'min_size' => $item?->min_group_size,
                    'max_size' => $item?->max_group_size,
                    'members' => $r->participants->map(fn (StateFestParticipant $p) => [
                        'id' => $p->id,
                        'name' => $p->student_name,
                        'class_name' => $p->class_name,
                        'chest_number' => $p->chest_number,
                        'is_leader' => (bool) $p->is_leader,
                        'is_standby' => (bool) $p->is_standby,
                        'withdrawn' => $p->withdrawn_at !== null,
                    ])->values(),
                    'competing' => $competing->count(),
                    'standbys' => $r->participants->where('is_standby', true)->count(),
                    'has_leader' => $r->participants->where('is_leader', true)->where('withdrawn_at', null)->isNotEmpty(),
                    'size_problem' => $this->sizeProblem($item, $competing->count()),
                ];
            })
            ->sortBy(fn ($t) => [$t['sahodaya'], $t['item_code']])
            ->values();
    }

    /** Null when the team is a legal size; a sentence when it is not. */
    public function sizeProblem(?FestStateProgramItem $item, int $competing): ?string
    {
        if (! $item) {
            return null;
        }

        if ($item->min_group_size && $competing < $item->min_group_size) {
            return "Needs {$item->min_group_size}; has {$competing} competing.";
        }

        if ($item->max_group_size && $competing > $item->max_group_size) {
            return "Allows {$item->max_group_size}; has {$competing} competing.";
        }

        return null;
    }

    /** Set who leads a team. Exactly one leader, so naming a new one stands the previous one down. */
    public function setLeader(StateFestRegistration $registration, int $participantId): void
    {
        $participant = $registration->participants()->findOrFail($participantId);

        if ($participant->is_standby) {
            throw ValidationException::withMessages(['leader' => 'A standby cannot lead the team.']);
        }

        DB::connection('state')->transaction(function () use ($registration, $participant) {
            $registration->participants()->update(['is_leader' => false]);
            $participant->forceFill(['is_leader' => true])->save();
        });
    }

    public function setStandby(StateFestRegistration $registration, int $participantId, bool $isStandby): void
    {
        $participant = $registration->participants()->findOrFail($participantId);

        if ($isStandby && $participant->is_leader) {
            throw ValidationException::withMessages([
                'standby' => 'Name another leader before making this member a standby.',
            ]);
        }

        $participant->forceFill(['is_standby' => $isStandby])->save();
    }

    /**
     * Request a substitution. Records it; does not apply it — a State officer decides.
     *
     * @param  array{original_participant_id: int, substitute_name: string, substitute_class?: ?string, reason: string, evidence_path?: ?string, user_id?: ?int, user_name?: ?string}  $data
     */
    public function requestSubstitution(StateFestEvent $event, StateFestRegistration $registration, array $data): StateSubstitution
    {
        $this->assertSubstitutionWindowOpen($event);

        $original = $registration->participants()->findOrFail($data['original_participant_id']);

        if ($original->withdrawn_at) {
            throw ValidationException::withMessages([
                'original' => "{$original->student_name} has already been replaced.",
            ]);
        }

        // Eligibility: the substitute must not already be in this entry, which would be a duplicate
        // rather than a replacement.
        $duplicate = $registration->participants()
            ->whereRaw('lower(student_name) = ?', [strtolower(trim($data['substitute_name']))])
            ->whereNull('withdrawn_at')
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'substitute_name' => "{$data['substitute_name']} is already in this entry.",
            ]);
        }

        return StateSubstitution::create([
            'id' => (string) Str::uuid(),
            'state_event_id' => $event->id,
            'state_id' => $event->state_id,
            'sahodaya_id' => $registration->sahodaya_id,
            'registration_id' => $registration->id,
            'original_participant_id' => $original->id,
            'original_name' => $original->student_name,
            'substitute_name' => trim($data['substitute_name']),
            'substitute_class' => $data['substitute_class'] ?? $original->class_name,
            'school_id' => $registration->school_id,
            'school_name' => $registration->school_name,
            'item_code' => $registration->item_code,
            'reason' => $data['reason'],
            'evidence_path' => $data['evidence_path'] ?? null,
            'status' => 'requested',
            'requested_by_user_id' => $data['user_id'] ?? null,
            'requested_by_name' => $data['user_name'] ?? null,
        ]);
    }

    /**
     * Approve a substitution and apply it.
     *
     * The original is withdrawn rather than deleted and the substitute added as a new participant,
     * so the record shows who was replaced by whom. The original keeps its chest number only if the
     * substitute inherits it — which they do, because the number is printed on a sheet somewhere.
     */
    public function approveSubstitution(StateSubstitution $substitution, array $context = []): StateSubstitution
    {
        if (! $substitution->isPending()) {
            throw ValidationException::withMessages(['substitution' => 'This substitution has already been decided.']);
        }

        return DB::connection('state')->transaction(function () use ($substitution, $context) {
            $original = StateFestParticipant::find($substitution->original_participant_id);
            $chestNumber = $original?->chest_number;

            if ($original) {
                // The chest number moves to the substitute, so it has to be released first:
                // (state_event_id, chest_number) is unique, and the number is already printed on
                // attendance sheets and ID cards, so reissuing a different one would invalidate them.
                $original->forceFill(['withdrawn_at' => now(), 'chest_number' => null])->save();
            }

            StateFestParticipant::create([
                'state_event_id'  => $substitution->state_event_id,
                'registration_id' => $substitution->registration_id,
                'student_name'    => $substitution->substitute_name,
                'class_name'      => $substitution->substitute_class,
                'chest_number'    => $chestNumber,
                'is_leader'       => (bool) $original?->is_leader,
                'is_standby'      => false,
                'meta'            => ['substituted_for' => $substitution->original_name],
            ]);

            $substitution->forceFill([
                'status' => 'approved',
                'decision_note' => $context['note'] ?? null,
                'decided_by_user_id' => $context['user_id'] ?? null,
                'decided_by_name' => $context['user_name'] ?? null,
                'decided_at' => now(),
            ])->save();

            return $substitution->fresh();
        });
    }

    public function rejectSubstitution(StateSubstitution $substitution, array $context = []): StateSubstitution
    {
        if (! $substitution->isPending()) {
            throw ValidationException::withMessages(['substitution' => 'This substitution has already been decided.']);
        }

        if (blank($context['note'] ?? null)) {
            throw ValidationException::withMessages([
                'note' => 'Say why the substitution is refused — the Sahodaya sees this.',
            ]);
        }

        $substitution->forceFill([
            'status' => 'rejected',
            'decision_note' => $context['note'],
            'decided_by_user_id' => $context['user_id'] ?? null,
            'decided_by_name' => $context['user_name'] ?? null,
            'decided_at' => now(),
        ])->save();

        return $substitution->fresh();
    }

    /**
     * Substitutions close with the scrutiny window: after the State has finished deciding who is
     * competing, a change of participant is an appeal, not a correction.
     */
    private function assertSubstitutionWindowOpen(StateFestEvent $event): void
    {
        if ($reason = $this->settings->windowClosedReason($event, StateEventSettings::WINDOW_SCRUTINY)) {
            throw ValidationException::withMessages([
                'substitution' => $reason.' Substitutions close with scrutiny.',
            ]);
        }
    }
}
