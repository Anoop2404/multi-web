<?php

namespace App\Services\State;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Models\StateRemittance;
use App\Models\User;
use App\Services\Auth\UserCredentialService;
use App\Services\Notifications\NotificationService;
use App\Support\ExcelImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    public function __construct(private UserCredentialService $credentials) {}

    public function createSahodaya(FestStateProgram $program, array $data): ExternalSahodaya
    {
        $sahodaya = ExternalSahodaya::create([
            'state_program_id' => $program->id,
            'name'             => $data['name'],
            'district'         => $data['district'] ?? null,
            'contact_name'     => $data['contact_name'] ?? null,
            'contact_phone'    => $data['contact_phone'] ?? null,
            'contact_email'    => $data['contact_email'] ?? null,
            'access_code'      => ExternalSahodaya::generateAccessCode(),
            'status'           => 'active',
            'source'           => $data['source'] ?? 'manual',
            'is_appeal_pool'   => $data['is_appeal_pool'] ?? false,
        ]);

        $this->ensureAppealSchool($sahodaya);

        return $sahodaya;
    }

    /**
     * Every Sahodaya (outside or managed — see Tenant::ensureAppealPoolSchool() for the
     * managed-tenant equivalent) gets one placeholder "Appeal School" it can file a
     * court-order/wildcard entry under without crediting that student's real school.
     */
    public function ensureAppealSchool(ExternalSahodaya $sahodaya): ExternalSchool
    {
        $existing = $sahodaya->schools()->where('is_appeal_pool', true)->first();
        if ($existing) {
            return $existing;
        }

        return $sahodaya->schools()->create([
            'name'           => 'Appeal School',
            'username'       => $this->generateSchoolUsername("{$sahodaya->name}-appeal"),
            'access_code'    => ExternalSchool::generateAccessCode(),
            'password'       => $this->credentials->generateTemporaryPassword(),
            'status'         => 'active',
            'is_appeal_pool' => true,
        ]);
    }

    /**
     * One shared "Appeal Sahodaya" per state program — for a student admitted at State
     * level by court order despite not qualifying through the normal Sahodaya pipeline
     * (docs conversation, 2026-09-14). Reuses the exact same coordinator portal as any
     * other outside Sahodaya: the state admin (or whoever handles the case) logs in with
     * its access code and adds an entry, typing the student's real Sahodaya/school name
     * as the "school" — traceable, but never counted toward that real school/Sahodaya's
     * totals since it never flows through the normal qualification/promotion path.
     */
    public function ensureAppealSahodaya(FestStateProgram $program): ExternalSahodaya
    {
        $existing = ExternalSahodaya::where('state_program_id', $program->id)
            ->where('is_appeal_pool', true)
            ->first();
        if ($existing) {
            return $existing;
        }

        return $this->createSahodaya($program, [
            'name'           => 'Appeal Sahodaya',
            'source'         => 'system',
            'is_appeal_pool' => true,
        ]);
    }

    public function addSchool(ExternalSahodaya $sahodaya, array $data): ExternalSchool
    {
        $plainPassword = $this->credentials->generateTemporaryPassword();

        return $sahodaya->schools()->create([
            'name'           => $data['name'],
            'username'       => $this->generateSchoolUsername($data['name']),
            'contact_name'   => $data['contact_name'] ?? null,
            'contact_phone'  => $data['contact_phone'] ?? null,
            'access_code'    => ExternalSchool::generateAccessCode(),
            'password'       => $plainPassword,
            'plain_password' => $plainPassword,
            'status'         => 'active',
        ]);
    }

    /** Slugified from the school name, with a numeric suffix on collision. */
    private function generateSchoolUsername(string $name): string
    {
        $base = Str::slug($name, '.') ?: 'school';
        $username = $base;
        $suffix = 1;

        while (ExternalSchool::where('username', $username)->exists()) {
            $suffix++;
            $username = "{$base}{$suffix}";
        }

        return $username;
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
        try {
            return $this->createEntry($school, $data);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }
    }

    /**
     * Core of addEntry() without the HTTP abort() — throws \DomainException on a business-rule
     * violation (quota exceeded, duplicate student) so callers can decide how to surface it.
     * The single-entry portal form (addEntry) turns that into a 422; the bulk importer
     * (importWinnersFromSpreadsheet) catches it per-row and keeps going.
     *
     * @param  array{item_id?: string, item_code: string, item_name?: string, student_name: string, class_name?: string, position?: int, grade?: string, qualify_count?: int}  $data
     */
    private function createEntry(ExternalSchool $school, array $data): StateQualifierEntry
    {
        $sahodaya = $school->sahodaya;
        $intake = $this->openDraftIntake($sahodaya);

        $itemEntries = $intake->entries()->where('item_code', $data['item_code']);

        if (! empty($data['qualify_count']) && $itemEntries->clone()->count() >= (int) $data['qualify_count']) {
            throw new \DomainException("This Sahodaya has already reached the qualifier limit ({$data['qualify_count']}) for item {$data['item_code']} across all schools.");
        }

        if ($itemEntries->clone()->where('student_name', $data['student_name'])->exists()) {
            throw new \DomainException("{$data['student_name']} is already entered for item {$data['item_code']}.");
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

    /**
     * Bulk winner-list upload — the coordinator uploads ONE spreadsheet covering every school
     * under their Sahodaya (columns: school_name, category/class, student_name, roll_number)
     * instead of setting up schools first and having each log in separately. Unique school
     * names auto-create ExternalSchool rows. This is a ROSTER upload only — no item_code, no
     * position: which item each student is entered for is a deliberate, separate step
     * (registerToItem()) the coordinator does afterward, same shape as the existing school-level
     * "Student Registry, then register to items" flow (chat decision, 2026-09-14) rather than
     * trusting an item_code column in the spreadsheet.
     *
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importWinnersFromSpreadsheet(ExternalSahodaya $sahodaya, string $path): array
    {
        $parsed = ExcelImport::associativeRows($path);
        $rows = $parsed['rows'];

        if ($rows === []) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['No data rows found.']];
        }

        $intake = $this->openDraftIntake($sahodaya);
        $schoolsByName = $sahodaya->schools()->get()->keyBy(fn ($s) => strtolower($s->name));

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $lineNum => $row) {
            $line = $lineNum + 2;
            $schoolName = trim($row['school_name'] ?? $row['school'] ?? '');
            $studentName = trim($row['student_name'] ?? $row['student'] ?? '');
            $category = trim($row['category'] ?? $row['class_name'] ?? $row['class'] ?? '');
            $rollNumber = trim($row['roll_number'] ?? $row['roll_no'] ?? '');

            if ($schoolName === '' || $studentName === '') {
                $errors[] = "Row {$line}: school and student are both required.";
                $skipped++;

                continue;
            }

            $school = $schoolsByName->get(strtolower($schoolName));
            if (! $school) {
                $school = $this->addSchool($sahodaya, ['name' => $schoolName]);
                $schoolsByName->put(strtolower($schoolName), $school);
            }

            $duplicate = $intake->entries()
                ->whereNull('item_code')
                ->where('school_id', $school->id)
                ->where('student_name', $studentName)
                ->exists();

            if ($duplicate) {
                $errors[] = "Row {$line}: {$studentName} ({$schoolName}) is already on the roster.";
                $skipped++;

                continue;
            }

            $intake->entries()->create([
                'school_id'      => $school->id,
                'school_name'    => $school->name,
                'student_name'   => $studentName,
                'class_name'     => $category !== '' ? $category : null,
                'roll_number'    => $rollNumber !== '' ? $rollNumber : null,
                'qualifier_type' => 'external_entry',
                'status'         => 'pending',
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * The Sahodaya coordinator registers an already-uploaded roster student to a specific
     * state-level item — the separate step after importWinnersFromSpreadsheet(), matching the
     * existing school-level "Student Registry, then register to items" flow. Fills in the
     * item_id/item_code/item_name/position/grade on that student's existing roster entry rather
     * than creating a new one. Enforces the same per-item qualifier quota (e.g. top-2, or top-1
     * for English One Act Play) across the whole Sahodaya that the single-entry school form and
     * the managed-tenant outbox path both enforce.
     */
    public function registerToItem(ExternalSahodaya $sahodaya, StateQualifierEntry $entry, string $itemCode, ?int $position = null, ?string $grade = null): StateQualifierEntry
    {
        $school = ExternalSchool::find($entry->school_id);
        abort_if(! $school || $school->external_sahodaya_id !== $sahodaya->id, 403);
        abort_unless($entry->intake?->status === 'draft', 422, 'This batch has already been submitted to State.');
        abort_unless($entry->item_code === null, 422, "{$entry->student_name} is already registered for {$entry->item_name}.");

        $item = FestStateProgramItem::where('state_program_id', $sahodaya->state_program_id)
            ->where('item_code', $itemCode)
            ->first();
        abort_unless($item, 422, 'Select an item from the list.');

        $intake = $entry->intake;
        $itemEntries = $intake->entries()->where('item_code', $item->item_code);

        if (! empty($item->qualify_count) && $itemEntries->clone()->count() >= (int) $item->qualify_count) {
            abort(422, "This Sahodaya has already reached the qualifier limit ({$item->qualify_count}) for item {$item->item_code} across all schools.");
        }

        $entry->update([
            'item_id'   => $item->id,
            'item_code' => $item->item_code,
            'item_name' => $item->title,
            'position'  => $position,
            'grade'     => $grade,
        ]);

        return $entry->fresh();
    }

    /** Roster students not yet registered to any item, for the coordinator's "register to items" screen. */
    public function unassignedRoster(ExternalSahodaya $sahodaya): \Illuminate\Support\Collection
    {
        $intake = $this->draftIntake($sahodaya);
        if (! $intake) {
            return collect();
        }

        return $intake->entries()->whereNull('item_code')->orderBy('school_name')->orderBy('student_name')->get();
    }

    /**
     * Custom-amount, single proof-upload fee (chat decision, 2026-09-14) — deliberately not a
     * calculated fee schedule: the Sahodaya declares its own amount and uploads one proof
     * covering the whole roster. Reuses StateRemittance (the same table/verify/reject/
     * download-proof flow managed Sahodayas' calculated fee demands already use — see
     * StateRemittanceController) instead of a parallel mechanism, keyed the same way
     * StateQualifierIntake.source_tenant_id already tells managed and outside submissions apart.
     */
    public function submitFee(ExternalSahodaya $sahodaya, float $amount, UploadedFile $proof): StateRemittance
    {
        abort_if($sahodaya->is_appeal_pool, 422, 'The Appeal Sahodaya does not submit a registration fee.');

        $existing = StateRemittance::where('sahodaya_id', "external:{$sahodaya->id}")->first();
        abort_if($existing?->status === 'verified', 422, 'Your fee has already been verified. Contact the State Kalolsavam office to make changes.');

        $disk = config('filesystems.upload_disk', 'shared');
        $path = $proof->store("external-sahodayas/{$sahodaya->id}", $disk);
        abort_if($path === false, 500, 'Payment proof upload failed. Please try again.');

        return StateRemittance::updateOrCreate(
            ['sahodaya_id' => "external:{$sahodaya->id}"],
            [
                'state_id'         => $sahodaya->program?->state_id,
                'title'            => 'State Kalolsavam registration fee',
                'academic_year'    => $sahodaya->program?->academic_year,
                'amount'           => $amount,
                'status'           => 'submitted',
                'proof_path'       => $path,
                'payment_date'     => now()->toDateString(),
                'rejection_reason' => null,
                'reviewed_by'      => null,
                'reviewed_at'      => null,
            ],
        );
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

        $unassignedCount = $intake->entries()->whereNull('item_code')->count();
        abort_if($unassignedCount > 0, 422, "{$unassignedCount} uploaded student(s) aren't registered to an item yet. Register them (or remove them) before submitting.");

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
