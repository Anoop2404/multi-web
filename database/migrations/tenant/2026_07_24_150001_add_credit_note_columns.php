<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A FestFeeCredit/ProgramFeeCredit row tracked money owed back to a school, but until now
 * had no actual document a school could see, download, or point to — just a number on a
 * badge. Adds the same numbered-document fields FeeReceipt already carries
 * (receipt_number / generated_receipt_path) so CreditNoteService can generate, store, and
 * link a real credit note the same way ProgramFeeReceiptService does for payment receipts.
 * See docs/FLOW_GAP_FIX_PLAN.md Phase 3b.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['fest_fee_credits', 'program_fee_credits'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'credit_note_number')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('credit_note_number')->nullable()->after('reason');
                    $t->string('generated_note_path')->nullable()->after('credit_note_number');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['fest_fee_credits', 'program_fee_credits'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'credit_note_number')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn(['credit_note_number', 'generated_note_path']);
                });
            }
        }
    }
};
