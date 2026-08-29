<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationBatchFeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrective recalculation for a phased_regional_billing event, after a billing-logic fix
 * (the whole-event quota engine + once-per-event school fee — see
 * FestRegistrationBatchFeeService::recalculateBatch()). Dry-run by default: recomputes every
 * school's OLD vs NEW total_due per batch + combined rollup inside a transaction that's
 * always rolled back, so nothing is written unless --commit is given. --commit applies it
 * for real, passing force: true through recalculateAll() so the "paid invoices are
 * immutable" guard doesn't silently skip exactly the schools who've already paid something —
 * amount_paid itself is never touched by recalculation either way, only
 * total_due/participation_fee/school_registration_fee.
 */
class FestRecalculateBatchBilling extends Command
{
    protected $signature = 'fest:recalculate-batch-billing
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : Root fest_events id to recalculate (required)}
        {--commit : Persist the recalculated totals (defaults to dry-run)}';

    protected $description = 'Recompute every school\'s batch-billing invoice for a phased_regional_billing event, showing old vs new totals';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $commit = (bool) $this->option('commit');

        if (! $sahodayaOpt || ! $eventOpt) {
            $this->error('Both --sahodaya and --event are required.');

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

        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use ($eventOpt, $commit, &$exitCode) {
                $exitCode = $this->recalculate((int) $eventOpt, $commit);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function recalculate(int $eventId, bool $commit): int
    {
        $root = FestEvent::find($eventId);
        if (! $root) {
            $this->error("No fest_events row with id={$eventId} in this tenant.");

            return self::FAILURE;
        }

        if ($root->workflow_mode !== \App\Services\Events\FestPhasedWorkflowService::MODE) {
            $this->error("Event #{$eventId} is not using phased_regional_billing (workflow_mode={$root->workflow_mode}).");

            return self::FAILURE;
        }

        $schoolIds = FestSchoolEventFee::where('event_id', $root->id)->distinct()->pluck('school_id');
        $service = app(FestRegistrationBatchFeeService::class);

        $rows = [];
        $changedCount = 0;

        // Everything below runs inside one manually-managed transaction so a dry-run can
        // compute the REAL recalculation (reusing recalculateAll() itself, not a
        // reimplementation that could drift) and then discard it — recalculateBatch()'s own
        // inner DB::transaction() calls nest as savepoints under this outer one, and rolling
        // this back undoes those too.
        DB::beginTransaction();

        foreach ($schoolIds as $schoolId) {
            $before = FestSchoolEventFee::where('event_id', $root->id)
                ->where('school_id', $schoolId)
                ->get()
                ->keyBy(fn (FestSchoolEventFee $f) => $f->registration_batch_id ?? 'rollup');

            $after = $service->recalculateAll($root, $schoolId, force: true)
                ->keyBy(fn (FestSchoolEventFee $f) => $f->registration_batch_id ?? 'rollup');
            $rollupAfter = FestSchoolEventFee::where('event_id', $root->id)
                ->where('school_id', $schoolId)
                ->whereNull('registration_batch_id')
                ->first();
            if ($rollupAfter) {
                $after->put('rollup', $rollupAfter);
            }

            $school = Tenant::find($schoolId);
            foreach ($after as $key => $fee) {
                $beforeFee = $before->get($key);
                $oldTotal = $beforeFee ? round((float) $beforeFee->total_due, 2) : null;
                $newTotal = round((float) $fee->total_due, 2);
                if ($oldTotal !== null && $oldTotal !== $newTotal) {
                    $changedCount++;
                }

                $rows[] = [
                    'school' => $school?->name ?? $schoolId,
                    'row' => $key === 'rollup' ? 'COMBINED' : ($fee->registrationBatch?->name ?? $key),
                    'old_total_due' => $oldTotal !== null ? number_format($oldTotal, 2) : '—',
                    'new_total_due' => number_format($newTotal, 2),
                    'changed' => ($oldTotal !== null && $oldTotal !== $newTotal) ? 'YES' : '',
                    'amount_paid' => number_format((float) $fee->amount_paid, 2).' (unchanged)',
                ];
            }
        }

        if ($commit) {
            DB::commit();
            $this->info('Committed — totals above are now live.');
        } else {
            DB::rollBack();
            $this->warn('DRY RUN — nothing was written. Re-run with --commit to apply.');
        }

        $this->table(
            ['School', 'Level', 'Old total due (₹)', 'New total due (₹)', 'Changed', 'Amount paid'],
            $rows,
        );
        $this->info(count($schoolIds)." school(s) recalculated, {$changedCount} row(s) with a different total_due.");

        return self::SUCCESS;
    }
}
