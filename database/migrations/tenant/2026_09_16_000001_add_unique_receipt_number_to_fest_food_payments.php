<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Defense-in-depth for the receipt-number race described in FestFoodPayment::
 * generateReceiptNumber()'s docblock (Phase 4 audit item 5): even with the transaction
 * fix in FestFoodPayment::recordForBill(), a DB-level unique constraint is what actually
 * guarantees two concurrent inserts can never land the same receipt_number, rather than
 * relying solely on application-level locking. receipt_number already embeds the bill id
 * (e.g. "FB123-001"), so it's meant to be globally unique across the whole table, not just
 * per-bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_food_payments')) {
            return;
        }

        // Guard against pre-existing duplicate receipt numbers (from the race this
        // migration closes) blocking the unique index from being created — leaves any
        // found duplicates as-is (nulled/blank receipt numbers are still allowed, just not
        // duplicate non-null ones) rather than silently deleting payment history.
        $duplicateCount = \DB::table('fest_food_payments')
            ->whereNotNull('receipt_number')
            ->select('receipt_number')
            ->groupBy('receipt_number')
            ->havingRaw('count(*) > 1')
            ->get()
            ->count();

        if ($duplicateCount > 0) {
            \Illuminate\Support\Facades\Log::warning(
                "fest_food_payments has {$duplicateCount} duplicate receipt_number value(s) — skipping unique index creation. Resolve manually, then re-run this migration."
            );

            return;
        }

        Schema::table('fest_food_payments', function (Blueprint $table) {
            $table->unique('receipt_number', 'fest_food_payments_receipt_number_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_food_payments')) {
            return;
        }

        Schema::table('fest_food_payments', function (Blueprint $table) {
            $table->dropUnique('fest_food_payments_receipt_number_unique');
        });
    }
};
