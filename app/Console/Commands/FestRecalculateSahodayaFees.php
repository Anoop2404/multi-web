<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Services\Events\FestPhasedWorkflowService;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk corrective recalculation for one Sahodaya's ordinary (non phased_regional_billing)
 * events, after a billing-logic fix — e.g. the kalolsavam_composite per-student
 * free-quota exclusion fix. Reuses FestSchoolEventFeeService::recalculate() for every
 * school with a live registration on every (or one named) event, exactly the same call
 * the per-school "Recalculate" button and the automatic Settings→Fees-save job make —
 * this just fans it out over every school and every event in one shot instead of one
 * click at a time.
 *
 * Dry-run by default, same convention as fest:recalculate-batch-billing: the whole scan
 * runs inside a transaction that's rolled back unless --commit is given, so you can see
 * exactly what would change before touching anything live.
 *
 * phased_regional_billing events are deliberately excluded — those have their own
 * dedicated command (fest:recalculate-batch-billing) which correctly handles the
 * batch/rollup fee-row split; reusing plain recalculate() for them here would not.
 */
class FestRecalculateSahodayaFees extends Command
{
    protected $signature = 'fest:recalculate-sahodaya-fees
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : Limit to one fest_events id (omit to recalculate every eligible event for the Sahodaya)}
        {--commit : Persist the recalculated totals (defaults to dry-run)}';

    protected $description = 'Recompute every registered school\'s fee for every (or one) non-batch-billing event under a Sahodaya, showing old vs new totals';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $commit = (bool) $this->option('commit');

        if (! $sahodayaOpt) {
            $this->error('--sahodaya is required.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->where('type', 'sahodaya')
            ->where(function ($q) use ($sahodayaOpt) {
                $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
            })
            ->first();

        if (! $tenant) {
            $this->error("No matching Sahodaya tenant for '{$sahodayaOpt}'.");

            return self::FAILURE;
        }

        if (! $commit) {
            $this->info('Running in DRY-RUN mode. Use --commit to apply changes.');
        }

        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use ($eventOpt, $commit, &$exitCode) {
                $exitCode = $this->recalculate($eventOpt, $commit);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function recalculate(?string $eventOpt, bool $commit): int
    {
        $events = FestEvent::query()
            // workflow_mode is null for every ordinary event — a plain != comparison
            // would silently exclude those under SQL's three-valued NULL logic, so the
            // "not batch billing" condition has to explicitly allow null too. Wrapped in
            // its own closure so the OR doesn't leak out and swallow the --event filter
            // below (a bare ->where(...)->orWhereNull(...)->whereKey(...) would parse as
            // "mode != X OR (mode IS NULL AND id = Y)", not what's intended here).
            ->where(function ($q) {
                $q->where('workflow_mode', '!=', FestPhasedWorkflowService::MODE)
                    ->orWhereNull('workflow_mode');
            })
            ->when($eventOpt, fn ($q) => $q->whereKey($eventOpt))
            ->get();

        $skippedBatchBilling = $eventOpt
            ? FestEvent::whereKey($eventOpt)->where('workflow_mode', FestPhasedWorkflowService::MODE)->exists()
            : FestEvent::where('workflow_mode', FestPhasedWorkflowService::MODE)->exists();

        if ($events->isEmpty()) {
            $this->warn($eventOpt
                ? "Event #{$eventOpt} not found in this tenant (or it's a phased_regional_billing event — use fest:recalculate-batch-billing for that)."
                : 'No eligible events found in this tenant.');

            return self::SUCCESS;
        }

        if ($skippedBatchBilling) {
            $this->line('Note: phased_regional_billing event(s) in this tenant were skipped — use fest:recalculate-batch-billing for those.');
        }

        $service = app(FestSchoolEventFeeService::class);
        $rows = [];
        $totalSchools = 0;
        $totalChanged = 0;

        DB::beginTransaction();

        foreach ($events as $event) {
            $schoolIds = FestRegistration::whereIn('event_id', $event->reportableEventIds())
                ->whereIn('status', ['submitted', 'approved', 'pending_approval'])
                ->distinct()
                ->pluck('school_id');

            foreach ($schoolIds as $schoolId) {
                $totalSchools++;
                $before = FestSchoolEventFee::where('event_id', $event->id)->where('school_id', $schoolId)->first();
                $oldTotal = $before ? round((float) $before->total_due, 2) : null;

                $after = $service->recalculate($event, $schoolId);
                $newTotal = round((float) $after->total_due, 2);

                // A missing "before" row (no fee record existed yet) isn't a stale invoice
                // to flag — there was nothing wrong to begin with, just a first-time calc.
                if ($oldTotal === null || $oldTotal === $newTotal) {
                    continue;
                }

                $totalChanged++;
                $school = Tenant::find($schoolId);
                $rows[] = [
                    'event' => $event->title ?? "#{$event->id}",
                    'school' => $school?->name ?? $schoolId,
                    'old_total_due' => number_format($oldTotal, 2),
                    'new_total_due' => number_format($newTotal, 2),
                    'amount_paid' => number_format((float) $after->amount_paid, 2).' (unchanged)',
                    'status' => $after->status,
                ];
            }
        }

        if ($commit) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        if ($rows === []) {
            $this->info("Checked {$totalSchools} school registration(s) across {$events->count()} event(s) — every total_due already matches, nothing to fix.");

            return self::SUCCESS;
        }

        $this->table(
            ['Event', 'School', 'Old total due (₹)', 'New total due (₹)', 'Amount paid', 'Status after'],
            $rows,
        );

        if ($commit) {
            $this->info('Committed — totals above are now live.');
        } else {
            $this->warn('DRY RUN — nothing was written. Re-run with --commit to apply.');
        }
        $this->info("{$totalChanged} row(s) with a stale total_due, out of {$totalSchools} school registration(s) checked across {$events->count()} event(s).");
        $this->line('amount_paid is never touched by recalculation — only total_due/participation_fee/school_registration_fee. A school already fully paid + approved whose total_due increases will be moved back to "submitted" (see FestSchoolEventFeeService::demoteSiblingApprovals()) so it\'s visibly under-paid again, not silently left "approved" while owing more.');

        return self::SUCCESS;
    }
}
