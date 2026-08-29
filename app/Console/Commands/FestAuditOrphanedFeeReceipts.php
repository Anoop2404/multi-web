<?php

namespace App\Console\Commands;

use App\Models\FeeReceipt;
use App\Models\FestSchoolEventFee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Read-only audit for orphaned FeeReceipt rows — receipts whose feeable_id (a
 * fest_school_event_fees id) no longer resolves to anything, because that fee row's own
 * event was deleted (deleting a FestEvent cascades to delete its fest_school_event_fees
 * rows at the DB level — see FestRegistrationBatchFeeService docs — but fee_receipts has no
 * such cascade, since it's a polymorphic relation with no DB foreign key). The receipt
 * itself and its uploaded proof file are never deleted, just left pointing at nothing.
 * Since a receipt carries no school_id/event_id of its own, the best identifying signal is
 * uploaded_by_user_id -> that user's own tenant (almost always the school that uploaded it).
 * Never writes.
 */
class FestAuditOrphanedFeeReceipts extends Command
{
    protected $signature = 'fest:audit-orphaned-fee-receipts
        {--sahodaya= : Sahodaya tenant id or subdomain (required)}';

    protected $description = 'Read-only list of FeeReceipt rows whose fest_school_event_fees target no longer exists (e.g. after deleting an event)';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
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

        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use (&$exitCode) {
                $exitCode = $this->audit();
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $exitCode;
    }

    private function audit(): int
    {
        $liveFeeIds = FestSchoolEventFee::pluck('id');

        $orphaned = FeeReceipt::where('feeable_type', FestSchoolEventFee::class)
            ->whereNotIn('feeable_id', $liveFeeIds)
            ->orderByDesc('id')
            ->get();

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned fee receipts found — every fee_receipts row for FestSchoolEventFee still points at a live row.');

            return self::SUCCESS;
        }

        $uploaderIds = $orphaned->pluck('uploaded_by_user_id')->filter()->unique();
        $uploaders = User::whereIn('id', $uploaderIds)->get(['id', 'name', 'tenant_id'])->keyBy('id');
        $schoolNames = Tenant::whereIn('id', $uploaders->pluck('tenant_id')->filter()->unique())
            ->pluck('name', 'id');

        $rows = $orphaned->map(function (FeeReceipt $r) use ($uploaders, $schoolNames) {
            $uploader = $r->uploaded_by_user_id ? $uploaders->get($r->uploaded_by_user_id) : null;
            $schoolName = $uploader?->tenant_id ? ($schoolNames[$uploader->tenant_id] ?? $uploader->tenant_id) : null;

            return [
                'id' => $r->id,
                'old_feeable_id' => $r->feeable_id,
                'likely_school' => $schoolName ?? '(unknown — no uploader on file)',
                'amount' => number_format((float) $r->amount, 2),
                'status' => $r->status,
                'transaction_ref' => $r->transaction_ref,
                'payment_date' => $r->payment_date?->toDateString(),
                'uploaded_at' => $r->created_at?->toDateString(),
            ];
        });

        $this->table(
            ['Receipt id', 'Old fee id', 'Likely school', 'Amount (₹)', 'Status', 'Txn ref', 'Payment date', 'Uploaded'],
            $rows->all(),
        );

        $this->newLine();
        $this->info($orphaned->count().' orphaned receipt(s) found. The uploaded proof files are untouched — none of this data is lost.');
        $this->line('To relink one to a current fest_school_event_fees row, use fest:relink-orphaned-fee-receipt (not built yet — tell me which receipt id + which school/event/level once you\'ve reviewed this list).');

        return self::SUCCESS;
    }
}
