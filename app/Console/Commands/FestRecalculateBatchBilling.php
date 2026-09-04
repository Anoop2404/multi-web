<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationBatchFeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrective recalculation for phased_regional_billing events, after a billing-logic fix
 * (the whole-event quota engine + once-per-event school fee, or the "paid invoices are
 * immutable" guard silently freezing a total after new registrations came in post-payment
 * -- see FestRegistrationBatchFeeService::recalculateBatch()). Dry-run by default:
 * recomputes every school's OLD vs NEW total_due per batch + combined rollup inside a
 * transaction that's always rolled back, so nothing is written unless --commit is given.
 * --commit applies it for real, passing force: true through recalculateAll() so the "paid
 * invoices are immutable" guard doesn't silently skip exactly the schools who've already
 * paid something -- amount_paid itself is never touched by recalculation either way, only
 * total_due/participation_fee/school_registration_fee.
 *
 * --sahodaya and --event are optional filters, not requirements: omit --sahodaya to scan
 * every Sahodaya tenant, and/or omit --event to scan every phased_regional_billing root
 * event within whichever tenant(s) are in scope -- same "scan everything unless narrowed"
 * convention as fest:audit-event-topology.
 */
class FestRecalculateBatchBilling extends Command
{
    protected $signature = 'fest:recalculate-batch-billing
        {--sahodaya= : Sahodaya tenant id or subdomain (omit to scan every Sahodaya)}
        {--event= : Root fest_events id to recalculate (omit to scan every phased_regional_billing event in scope)}
        {--commit : Persist the recalculated totals (defaults to dry-run)}';

    protected $description = 'Recompute every school\'s batch-billing invoice for phased_regional_billing event(s), showing old vs new totals';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $commit = (bool) $this->option('commit');

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error($sahodayaOpt ? "No matching Sahodaya tenant for '{$sahodayaOpt}'." : 'No Sahodaya tenants found.');

            return self::FAILURE;
        }

        if (! $commit) {
            $this->info('Running in DRY-RUN mode. Use --commit to apply changes.');
        }

        $allRows = [];
        $totalSchools = 0;
        $totalChanged = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $eventOpt, $commit, &$allRows, &$totalSchools, &$totalChanged) {
                    $roots = FestEvent::query()
                        ->whereNull('parent_event_id')
                        ->where('workflow_mode', \App\Services\Events\FestPhasedWorkflowService::MODE)
                        ->when($eventOpt, fn ($q) => $q->whereKey($eventOpt))
                        ->get();

                    foreach ($roots as $root) {
                        [$rows, $schoolCount, $changedCount] = $this->recalculate($tenant, $root, $commit);
                        $allRows = array_merge($allRows, $rows);
                        $totalSchools += $schoolCount;
                        $totalChanged += $changedCount;
                    }
                });
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        if ($allRows === []) {
            $this->warn($eventOpt
                ? "Event #{$eventOpt} not found, or isn't using phased_regional_billing, in the tenant(s) checked."
                : 'No phased_regional_billing events found in the tenant(s) checked.');

            return self::SUCCESS;
        }

        $this->table(
            ['Sahodaya', 'Event', 'School', 'Level', 'Old total due (₹)', 'New total due (₹)', 'Changed', 'Amount paid'],
            $allRows,
        );

        if ($commit) {
            $this->info('Committed — totals above are now live.');
        } else {
            $this->warn('DRY RUN — nothing was written. Re-run with --commit to apply.');
        }
        $this->info("{$totalSchools} school(s) recalculated across ".count($tenants)." Sahodaya(s), {$totalChanged} row(s) with a different total_due.");

        return self::SUCCESS;
    }

    /** @return array{0: list<array<string, string>>, 1: int, 2: int} */
    private function recalculate(Tenant $tenant, FestEvent $root, bool $commit): array
    {
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
                    'sahodaya' => $tenant->name ?? $tenant->id,
                    'event' => $root->title ?? "#{$root->id}",
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
        } else {
            DB::rollBack();
        }

        return [$rows, $schoolIds->count(), $changedCount];
    }
}
