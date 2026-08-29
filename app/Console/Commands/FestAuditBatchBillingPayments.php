<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistrationBatch;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Read-only audit for a phased_regional_billing (registration-batch) event: for every
 * school with a fee row, shows the pre-conversion "rollup" row's own historic receipts
 * alongside every per-batch (Level 1/Level 2/...) row's own paid state, and the combined
 * figure FestRegistrationBatchFeeService::syncRollup() computes from both. Never writes.
 * Run before AND after fest:recalculate-batch-billing to visually confirm no pre-conversion
 * payment went missing across the fix.
 */
class FestAuditBatchBillingPayments extends Command
{
    protected $signature = 'fest:audit-batch-billing-payments
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}
        {--event= : Root fest_events id to check (required)}';

    protected $description = 'Read-only audit of a phased_regional_billing event\'s combined vs per-level payment state, per school';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');

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
            $tenant->run(function () use ($eventOpt, &$exitCode) {
                $exitCode = $this->audit((int) $eventOpt);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function audit(int $eventId): int
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

        $batches = FestRegistrationBatch::where('event_id', $root->id)->orderBy('sort_order')->get();
        $fees = FestSchoolEventFee::where('event_id', $root->id)
            ->with(['school:id,name', 'receipts'])
            ->get()
            ->groupBy('school_id');

        $this->info("Event #{$root->id} — {$root->title}");
        $this->info('Batches: '.$batches->pluck('name')->implode(', '));
        $this->newLine();

        $rows = [];
        foreach ($fees as $schoolId => $schoolFees) {
            $rollup = $schoolFees->first(fn (FestSchoolEventFee $f) => $f->registration_batch_id === null);
            $schoolName = $schoolFees->first()->school?->name ?? $schoolId;

            $rollupOwnReceipts = $rollup
                ? $rollup->receipts->where('status', 'approved')->sum('amount')
                : 0.0;

            $rows[] = [
                'school' => $schoolName,
                'row' => 'COMBINED (rollup)',
                'own_receipts' => number_format((float) $rollupOwnReceipts, 2),
                'total_due' => $rollup ? number_format((float) $rollup->total_due, 2) : '—',
                'amount_paid' => $rollup ? number_format((float) $rollup->amount_paid, 2) : '—',
                'status' => $rollup?->status ?? '—',
            ];

            foreach ($batches as $batch) {
                $fee = $schoolFees->first(fn (FestSchoolEventFee $f) => $f->registration_batch_id === $batch->id);
                $rows[] = [
                    'school' => '',
                    'row' => $batch->name,
                    'own_receipts' => $fee ? number_format((float) $fee->receipts->where('status', 'approved')->sum('amount'), 2) : '—',
                    'total_due' => $fee ? number_format((float) $fee->total_due, 2) : '—',
                    'amount_paid' => $fee ? number_format((float) $fee->amount_paid, 2) : '—',
                    'status' => $fee?->status ?? '—',
                ];
            }
        }

        $this->table(
            ['School', 'Row', 'Own approved receipts (₹)', 'Total due (₹)', 'Amount paid (₹)', 'Status'],
            $rows,
        );

        $preConversionCount = $fees->filter(function ($schoolFees) {
            $rollup = $schoolFees->first(fn (FestSchoolEventFee $f) => $f->registration_batch_id === null);

            return $rollup && $rollup->receipts->where('status', 'approved')->isNotEmpty();
        })->count();

        $this->newLine();
        $this->info("{$preConversionCount} school(s) have at least one approved receipt directly on the combined/rollup row (i.e. from before the phase conversion, or a payment made against the combined invoice).");
        $this->line('Nothing is ever deleted by this audit or by fest:recalculate-batch-billing — amount_paid is never touched by recalculation, only total_due/participation_fee/school_registration_fee.');

        return self::SUCCESS;
    }
}
