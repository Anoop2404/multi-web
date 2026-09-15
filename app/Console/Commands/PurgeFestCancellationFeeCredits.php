<?php

namespace App\Console\Commands;

use App\Models\FeeReceipt;
use App\Models\FestFeeCredit;
use App\Models\FestSchoolEventFee;
use App\Models\LedgerTransaction;
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
        {--event= : Optional event ID to scope the purge}
        {--dry-run : Run in inspection mode without deleting or modifying data}';

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
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('*** DRY RUN MODE ENABLED — No database changes will be saved. ***');
        }

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

        $this->info("Starting purge process across {$tenants->count()} Sahodaya tenant(s)...");

        // Loop over each Sahodaya tenant database
        foreach ($tenants as $tenant) {
            try {
                $tenant->run(fn () => $this->purgeTenant($eventId, $isDryRun, "Sahodaya: {$tenant->name} ({$tenant->id})"));
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

    private function purgeTenant(?string $eventId, bool $isDryRun, string $label): void
    {
        $this->line("--- Processing {$label} ---");

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

        $creditQuery = FestFeeCredit::query();
        if ($eventId) {
            $feeIds = FestSchoolEventFee::where('event_id', $eventId)->pluck('id');
            $creditQuery->whereIn('fest_school_event_fee_id', $feeIds);
        }
        $creditIds = $creditQuery->pluck('id');

        $receiptCount = $systemReceiptIds->count();
        $creditCount = $creditIds->count();

        $feeQuery = FestSchoolEventFee::query();
        if ($eventId) {
            $feeQuery->where('event_id', $eventId);
        }
        $fees = $feeQuery->get();

        if ($isDryRun) {
            $this->warn("[DRY-RUN] Would delete {$receiptCount} system credit receipts and {$creditCount} fee credit records.");
            $this->warn("[DRY-RUN] Would recalculate fee balances for {$fees->count()} school event fee records.");

            return;
        }

        DB::transaction(function () use ($systemReceiptQuery, $creditQuery, $systemReceiptIds, $creditIds) {
            if ($systemReceiptIds->isNotEmpty()) {
                LedgerTransaction::whereIn('reference_id', $systemReceiptIds)
                    ->where(function ($q) {
                        $q->where('reference_type', (new FeeReceipt)->getMorphClass())
                          ->orWhere('reference_type', FeeReceipt::class);
                    })
                    ->delete();
            }

            if ($creditIds->isNotEmpty()) {
                LedgerTransaction::whereIn('reference_id', $creditIds)
                    ->where(function ($q) {
                        $q->where('reference_type', (new FestFeeCredit)->getMorphClass())
                          ->orWhere('reference_type', FestFeeCredit::class);
                    })
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
