<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a FeeReceipt as system-generated (no real uploaded proof file) rather than a
 * school's actual bank/UPI payment — used by FestSchoolEventFeeService::applyAvailableCredit()
 * when an outstanding FestFeeCredit is automatically applied against a new balance. Consuming
 * code (FestSchoolEventFeeController::proof(), UI "View Proof" links) must check this flag
 * before attempting to serve/download `file_path`, since these rows carry a placeholder path,
 * not a real file on disk. See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_receipts', 'is_system_credit')) {
                $table->boolean('is_system_credit')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('fee_receipts', 'is_system_credit')) {
                $table->dropColumn('is_system_credit');
            }
        });
    }
};
