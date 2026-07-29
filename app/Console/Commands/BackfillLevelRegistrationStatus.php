<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestFeeCredit;
use App\Models\FestLevelRegistration;
use App\Models\FestParticipant;
use App\Models\Tenant;
use App\Services\Events\FestFeeLedgerService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Fees\CreditNoteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time data fix for the bug described in docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md:
 * cancelling/rejecting a student's last remaining item registration never used to touch
 * their FestLevelRegistration row (the "event athlete" record created the first time they
 * were added to any item — see FestLevelRegistrationService::issueForStudent()). Since the
 * composite fee model (FestSportsCompositeFeeService::calculate()/calculateForEvent()) counts
 * billable students primarily from FestLevelRegistration rows with status='active', any
 * student who had cancelled every item they were registered for BEFORE this fix landed is
 * still, right now, sitting in the database as an "active" registered student — and their
 * school is still being billed the per-student registration fee for them, permanently, with
 * zero items to show for it.
 *
 * This command finds every such stale row across all Sahodayas (or one, via --sahodaya),
 * flips it to 'withdrawn', and recalculates the affected school's fee. If the school had
 * already paid the (inflated) fee, the amount the recalculation frees up is recorded as a
 * FestFeeCredit + credit note — exactly the same "no touch to amount_paid/total_due
 * directly, just an auditable credit" pattern FestRegistrationService::cancelWithRefund() and
 * FestRegistrationBulkService::rejectMany() already use for the same situation going forward.
 *
 * Defaults to --dry-run reporting only; nothing is written unless you drop that flag.
 */
class BackfillLevelRegistrationStatus extends Command
{
    protected $signature = 'fest:backfill-level-registration-status
                            {--sahodaya= : Sahodaya tenant id}
                            {--event= : Single fest event id (within the Sahodaya DB)}
                            {--dry-run : Print what would change without saving or issuing credits}';

    protected $description = 'Deactivate stale FestLevelRegistration rows for students with zero remaining active items, and credit back any resulting fee reduction that was already paid';

    public function handle(): int
    {
        $sahodayaId = $this->option('sahodaya');
        $eventId = $this->option('event') ? (int) $this->option('event') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved, no credits issued.');
        }

        $sahodayas = $sahodayaId
            ? Tenant::query()->where('type', 'sahodaya')->whereKey($sahodayaId)->get()
            : Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get();

        if ($sahodayas->isEmpty()) {
            $this->error('No matching Sahodaya tenant found.');

            return self::FAILURE;
        }

        $totalStudents = 0;
        $totalSchools = 0;
        $totalCreditAmount = 0.0;

        foreach ($sahodayas as $sahodaya) {
            $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");

            try {
                $sahodaya->run(function () use ($eventId, $dryRun, &$totalStudents, &$totalSchools, &$totalCreditAmount) {
                    [$students, $schools, $creditAmount] = $this->processSahodaya($eventId, $dryRun);
                    $totalStudents += $students;
                    $totalSchools += $schools;
                    $totalCreditAmount += $creditAmount;
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$sahodaya->name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(
            ($dryRun ? 'Would deactivate' : 'Deactivated')." {$totalStudents} stale student registration(s) across {$totalSchools} school fee record(s)."
            .($totalCreditAmount > 0 ? ' Credit issued/would-issue: ₹'.number_format($totalCreditAmount, 2).'.' : '')
        );

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int, 2: float} [students deactivated, schools touched, total credit amount] */
    private function processSahodaya(?int $eventId, bool $dryRun): array
    {
        $events = FestEvent::query()
            ->when($eventId, fn ($q) => $q->whereKey($eventId))
            ->orderBy('id')
            ->get();

        $studentsTotal = 0;
        $schoolsTotal = 0;
        $creditTotal = 0.0;
        $rows = [];

        foreach ($events as $event) {
            $activeRegs = FestLevelRegistration::where('event_id', $event->id)
                ->where('status', 'active')
                ->get();

            if ($activeRegs->isEmpty()) {
                continue;
            }

            $staleBySchool = $activeRegs
                ->filter(fn (FestLevelRegistration $reg) => ! $this->studentStillActive($event, $reg->student_id))
                ->groupBy('school_id');

            if ($staleBySchool->isEmpty()) {
                continue;
            }

            $feeService = app(FestSchoolEventFeeService::class);

            foreach ($staleBySchool as $schoolId => $regs) {
                if (! $schoolId) {
                    continue;
                }

                $before = $feeService->currentFeeRecordFor($event, $schoolId);
                $dueBefore = (float) ($before?->total_due ?? 0);
                $paidBefore = (float) ($before?->amount_paid ?? 0);

                $rows[] = [
                    'event' => $event->title,
                    'school_id' => $schoolId,
                    'students' => $regs->count(),
                    'due_before' => number_format($dueBefore, 2),
                    'paid_before' => number_format($paidBefore, 2),
                ];

                $studentsTotal += $regs->count();
                $schoolsTotal++;

                if ($dryRun) {
                    continue;
                }

                $creditAmount = DB::transaction(function () use ($event, $schoolId, $regs, $feeService, $dueBefore, $paidBefore) {
                    FestLevelRegistration::whereIn('id', $regs->pluck('id'))->update(['status' => 'withdrawn']);

                    $after = $feeService->recalculate($event, $schoolId);
                    $reduction = round($dueBefore - (float) $after->total_due, 2);

                    if ($reduction <= 0 || $paidBefore <= 0) {
                        return 0.0;
                    }

                    $creditAmount = min($reduction, $paidBefore);
                    $credit = FestFeeCredit::create([
                        'fest_school_event_fee_id' => $after->id,
                        'source_registration_id' => null,
                        'amount' => $creditAmount,
                        'reason' => 'Backfill: student(s) with zero remaining active items were still being billed the per-student registration fee — see fest:backfill-level-registration-status',
                        'created_by_user_id' => auth()->id(),
                    ]);

                    app(FestFeeLedgerService::class)->postCreditIssued($credit);

                    try {
                        app(CreditNoteService::class)->issue($credit);
                    } catch (\Throwable) {
                        // credit is already recorded + posted; the note can be regenerated later
                    }

                    return $creditAmount;
                });

                $creditTotal += $creditAmount;
            }
        }

        if ($rows !== []) {
            $this->table(
                ['Event', 'School', 'Students', 'Due before', 'Paid before'],
                $rows,
            );
        } else {
            $this->line('  (nothing stale found)');
        }

        return [$studentsTotal, $schoolsTotal, $creditTotal];
    }

    /**
     * Same predicate as FestLevelRegistrationService::deactivateIfNoActiveItems() — kept as a
     * read-only check here (no writes) so the dry-run path can report without mutating.
     */
    private function studentStillActive(FestEvent $event, ?int $studentId): bool
    {
        if (! $studentId) {
            return false;
        }

        return FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->whereIn('event_id', $event->reportableEventIds())
                ->whereIn('status', ['submitted', 'approved']))
            ->where('participant_role', '!=', 'standby')
            ->where('student_id', $studentId)
            ->exists();
    }
}
