<?php

namespace App\Services\State;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Models\FestStateProgram;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Business logic for the outside-Sahodaya intake flow (docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1).
 *
 * Entries land in the exact same `state_qualifier_entries` table a managed Sahodaya's outbox
 * submission writes into — the only difference is *how* they get there (a coordinator/school
 * filling in a code-gated form instead of an automated API payload) and that they sit in a
 * 'draft' intake, invisible to State review, until the coordinator finalizes.
 */
class ExternalIntakeService
{
    public function createSahodaya(FestStateProgram $program, array $data): ExternalSahodaya
    {
        return ExternalSahodaya::create([
            'state_program_id' => $program->id,
            'name'             => $data['name'],
            'contact_name'     => $data['contact_name'] ?? null,
            'contact_phone'    => $data['contact_phone'] ?? null,
            'contact_email'    => $data['contact_email'] ?? null,
            'access_code'      => ExternalSahodaya::generateAccessCode(),
            'status'           => 'active',
        ]);
    }

    public function addSchool(ExternalSahodaya $sahodaya, array $data): ExternalSchool
    {
        return $sahodaya->schools()->create([
            'name'          => $data['name'],
            'contact_name'  => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'access_code'   => ExternalSchool::generateAccessCode(),
            'status'        => 'active',
        ]);
    }

    /**
     * A school adds one of its own qualified students. Creates (or reuses) the sahodaya's
     * still-open draft intake for this program and appends an entry to it.
     *
     * Enforces the item's qualify_count (e.g. top-2, or top-1 for English One Act Play) across
     * *all* schools under this Sahodaya's current draft batch — the same cap the managed-Sahodaya
     * outbox path enforces in FestStateQualifierPayloadBuilder. Also guards against an accidental
     * double submit of the same student for the same item.
     *
     * @param  array{item_id?: int, item_code: string, item_name?: string, student_name: string, class_name?: string, position?: int, grade?: string, qualify_count?: int}  $data
     */
    public function addEntry(ExternalSchool $school, array $data): StateQualifierEntry
    {
        $sahodaya = $school->sahodaya;
        $intake = $this->openDraftIntake($sahodaya);

        $itemEntries = $intake->entries()->where('item_code', $data['item_code']);

        if (! empty($data['qualify_count']) && $itemEntries->clone()->count() >= (int) $data['qualify_count']) {
            abort(422, "This Sahodaya has already reached the qualifier limit ({$data['qualify_count']}) for this item across all schools.");
        }

        if ($itemEntries->clone()->where('student_name', $data['student_name'])->exists()) {
            abort(422, "{$data['student_name']} is already entered for this item.");
        }

        return $intake->entries()->create([
            'school_id'      => $school->id,
            'school_name'    => $school->name,
            'item_id'        => $data['item_id'] ?? null,
            'item_code'      => $data['item_code'],
            'item_name'      => $data['item_name'] ?? null,
            'student_name'   => $data['student_name'],
            'class_name'     => $data['class_name'] ?? null,
            'position'       => $data['position'] ?? null,
            'grade'          => $data['grade'] ?? null,
            'qualifier_type' => 'external_entry',
            'status'         => 'pending',
        ]);
    }

    public function removeEntry(ExternalSchool $school, StateQualifierEntry $entry): void
    {
        abort_if($entry->school_id !== $school->id, 403);
        abort_unless($entry->intake?->status === 'draft', 422, 'This batch has already been submitted to State.');

        $entry->delete();
    }

    /** All entries across every school under this Sahodaya, for the coordinator's review screen. */
    public function entriesForReview(ExternalSahodaya $sahodaya): \Illuminate\Support\Collection
    {
        $intake = $this->draftIntake($sahodaya);

        if (! $intake) {
            return collect();
        }

        return $intake->entries()->orderBy('school_name')->orderBy('item_code')->get();
    }

    /**
     * Coordinator finalizes: the draft intake becomes a normal 'received' intake, now visible
     * in StateQualifierReviewController same as any managed-Sahodaya outbox submission.
     */
    public function submit(ExternalSahodaya $sahodaya): StateQualifierIntake
    {
        $intake = $this->draftIntake($sahodaya);

        abort_unless($intake, 422, 'No entries to submit yet.');
        abort_if($intake->entries()->count() === 0, 422, 'Add at least one student before submitting.');

        $intake->update(['status' => 'received']);
        $intake = $intake->fresh();

        $this->notifyStateAdmins($sahodaya, $intake);

        return $intake;
    }

    /**
     * State admins get no automated heads-up for managed-Sahodaya outbox submissions either —
     * they're expected to poll the review queue. For the external-intake path there's no cron
     * or dashboard badge doing that polling for them, so we push an in-app + email notification
     * on submit instead of leaving the batch to be found by chance.
     */
    private function notifyStateAdmins(ExternalSahodaya $sahodaya, StateQualifierIntake $intake): void
    {
        $recipients = User::role(['state_admin', 'state_staff'])->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $count = $intake->entries()->count();
        $title = "Outside Sahodaya submitted qualifiers: {$sahodaya->name}";
        $body = "{$sahodaya->name} submitted {$count} qualifier entr" . ($count === 1 ? 'y' : 'ies') . " for review.";
        $actionUrl = '/admin/state-programs'; // review queue lives off the state-programs area

        $notifier = app(NotificationService::class);
        foreach ($recipients as $recipient) {
            $notifier->notify($recipient, $title, $body, $actionUrl, ['in_app', 'email']);
        }
    }

    private function draftIntake(ExternalSahodaya $sahodaya): ?StateQualifierIntake
    {
        return StateQualifierIntake::where('source_tenant_id', "external:{$sahodaya->id}")
            ->where('state_program_id', $sahodaya->state_program_id)
            ->where('status', 'draft')
            ->first();
    }

    /**
     * Locks the sahodaya row for the duration of the get-or-create so two schools adding their
     * very first entry at the same moment can't each observe "no draft exists" and create two
     * separate draft intakes (§ logical-gaps review: this was an unguarded race before).
     */
    private function openDraftIntake(ExternalSahodaya $sahodaya): StateQualifierIntake
    {
        return DB::transaction(function () use ($sahodaya) {
            ExternalSahodaya::whereKey($sahodaya->id)->lockForUpdate()->first();

            return $this->draftIntake($sahodaya) ?? StateQualifierIntake::create([
                'state_program_id' => $sahodaya->state_program_id,
                'source_tenant_id' => "external:{$sahodaya->id}",
                'source_event_id'  => 0,
                'idempotency_key'  => (string) Str::uuid(),
                'status'           => 'draft',
                'payload'          => ['source' => 'external_intake', 'external_sahodaya_id' => $sahodaya->id],
            ]);
        });
    }
}
