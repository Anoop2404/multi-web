<?php

namespace App\Console\Commands;

use App\Models\FeeReceipt;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerJournalEntry;
use App\Models\Tenant;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeFestCancellationFeeCredits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fest:purge-cancellation-fee-credits 
        {--sahodaya= : Sahodaya tenant id or subdomain (omit to run across all Sahodayas)}
        {--event= : Optional event ID to scope the purge}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purges system fee credits and auto-applied credit receipts generated on cancellation/rejection and recalculates fee balances.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventId = $this->option('event');

        if ($sahodayaOpt) {
            $tenants = Tenant::where('type', 'sahodaya')
                ->where(function ($q) use ($sahodayaOpt) {
                    $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                })
                ->get();

            if ($tenants->isEmpty()) {
                $this->error("No matching Sahodaya tenant found for '{$sahodayaOpt}'.");

                return Command::FAILURE;
            }
        } else {
            $tenants = Tenant::where('type', 'sahodaya')->get();
        }

        $this->info("Starting purge across {$tenants->count()} Sahodaya tenant(s)...");

        // First run on current connection (for central/single DB)
        $this->purgeTenant($eventId, 'Central Connection');

        // Loop over each Sahodaya tenant database
        foreach ($tenants as $tenant) {
            try {
                $tenant->run(fn () => $this->purgeTenant($eventId, "Sahodaya: {$tenant->name} ({$tenant->id})"));
            } catch (\Throwable $e) {
                $this->error("Failed processing tenant {$tenant->id}: " . $e->getMessage());
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->info('Completed purge across all environments.');

        return Command::SUCCESS;
    }

    private function purgeTenant(?string $eventId, string $label): void
    {
        $this->line("--- Processing {$label} ---");

        $deletedReceipts = 0;
        $deletedCredits = 0;

        DB::transaction(function () use ($eventId, &$deletedReceipts, &$deletedCredits) {
            $systemReceiptQuery = FeeReceipt::query()
                ->where(function ($q) {
                    $q->where('is_system_credit', true)
                      ->orWhere('transaction_ref', 'CREDIT-OFFSET')
                      ->orWhere('file_path', 'system://fee-credit-adjustment');
                });

            if ($eventId) {
                $feeIds = FestSchoolEventFee::where('event_id', $eventId)->pluck('id');
                $systemReceiptQuery->where('feeable_type', FestSchoolEventFee::class)
                    ->whereIn('feeable_id', $feeIds);
            }

            $systemReceiptIds = $systemReceiptQuery->pluck('id');

            if ($systemReceiptIds->isNotEmpty()) {
                LedgerJournalEntry::whereIn('receipt_id', $systemReceiptIds)->delete();
            }

            $creditQuery = FestFeeCredit::query();
            if ($eventId) {
                $feeIds = FestSchoolEventFee::where('event_id', $eventId)->pluck('id');
                $creditQuery->whereIn('fest_school_event_fee_id', $feeIds);
            }
            $creditIds = $creditQuery->pluck('id');

            if ($creditIds->isNotEmpty()) {
                LedgerJournalEntry::where('creditable_type', FestFeeCredit::class)
                    ->whereIn('creditable_id', $creditIds)
                    ->delete();
            }

            $deletedReceipts = $systemReceiptQuery->delete();
            $deletedCredits = $creditQuery->delete();

            // Reset fee_receipt_id on FestSchoolEventFee if it pointed to a deleted receipt
            FestSchoolEventFee::query()
                ->whereNotNull('fee_receipt_id')
                ->whereNotIn('fee_receipt_id', FeeReceipt::select('id'))
                ->update(['fee_receipt_id' => null]);
        });

        $this->info("Deleted {$deletedReceipts} system credit receipts and {$deletedCredits} fee credit records.");

        $feeQuery = FestSchoolEventFee::query();
        if ($eventId) {
            $feeQuery->where('event_id', $eventId);
        }

        $fees = $feeQuery->get();
        if ($fees->isNotEmpty()) {
            $this->info("Recalculating fees for {$fees->count()} school event fee records...");

            $feeService = app(FestSchoolEventFeeService::class);
            foreach ($fees as $fee) {
                if ($fee->event) {
                    try {
                        $feeService->recalculate($fee->event, $fee->school_id);
                    } catch (\Throwable $e) {
                        $fee->refreshPaidState();
                    }
                } else {
                    $fee->refreshPaidState();
                }
            }
        }
    }
}
