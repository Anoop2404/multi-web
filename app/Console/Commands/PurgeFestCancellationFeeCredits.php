<?php

namespace App\Console\Commands;

use App\Models\FeeReceipt;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerJournalEntry;
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
    protected $signature = 'fest:purge-cancellation-fee-credits {--event= : Optional event ID to scope the purge}';

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
        $eventId = $this->option('event');

        $this->info('Starting purge of cancellation fee credits and system credit receipts...');

        DB::transaction(function () use ($eventId) {
            // Find system credit receipts
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

            // Delete ledger journal entries for these receipts & credits
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

            $this->info("Deleted {$deletedReceipts} system credit receipts and {$deletedCredits} fee credit records.");

            // Reset fee_receipt_id on FestSchoolEventFee if it pointed to a deleted receipt
            FestSchoolEventFee::query()
                ->whereNotNull('fee_receipt_id')
                ->whereNotIn('fee_receipt_id', FeeReceipt::select('id'))
                ->update(['fee_receipt_id' => null]);
        });

        // Recalculate fee balances and refresh paid state for all school event fees
        $feeQuery = FestSchoolEventFee::query();
        if ($eventId) {
            $feeQuery->where('event_id', $eventId);
        }

        $fees = $feeQuery->get();
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

        $this->info('Successfully purged cancellation fee credits and refreshed fee balances.');

        return Command::SUCCESS;
    }
}
