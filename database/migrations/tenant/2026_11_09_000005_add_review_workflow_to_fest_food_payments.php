<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Food payments were staff-entered only — a Sahodaya/host-school admin typed in "amount
 * received" and it counted immediately, with no proof, no review step, and no way for a
 * school to submit a payment themselves. This adds a review workflow (pending/approved/
 * rejected) plus proof-of-payment fields, so a school can submit a claim (with a UTR/proof
 * upload) that only counts toward the bill's amount_paid once a staff member on the
 * receiving side (Sahodaya or host school) approves it. Deliberately kept separate from the
 * accounting ledger, matching FestFoodPayment::voidPayment()'s existing "no ledger
 * integration yet" note — see that model's docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_food_payments', function (Blueprint $table) {
            // Existing rows (all staff-entered, already "real") default to approved so
            // FestFoodBill::recalculate()'s new status-filtered sum doesn't retroactively
            // zero out every bill's amount_paid.
            $table->string('status', 20)->default('approved')->after('payment_mode'); // pending, approved, rejected

            $table->string('transaction_ref')->nullable()->after('notes');
            $table->string('bank_name')->nullable()->after('transaction_ref');
            $table->string('proof_path')->nullable()->after('bank_name');

            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->after('proof_path');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by_user_id');

            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('submitted_at');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');

            $table->index(['bill_id', 'status']);
        });

        // Every payment that already exists was staff-entered and immediately real —
        // backfill it as already-reviewed by whoever recorded it, at the time it was
        // recorded, rather than leaving reviewed_by/reviewed_at null for rows that are
        // actually fully settled.
        \Illuminate\Support\Facades\DB::table('fest_food_payments')->update([
            'reviewed_by_user_id' => \Illuminate\Support\Facades\DB::raw('received_by_user_id'),
            'reviewed_at' => \Illuminate\Support\Facades\DB::raw('received_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('fest_food_payments', function (Blueprint $table) {
            $table->dropIndex(['bill_id', 'status']);
            $table->dropColumn([
                'status', 'transaction_ref', 'bank_name', 'proof_path',
                'submitted_by_user_id', 'submitted_at', 'reviewed_by_user_id', 'reviewed_at', 'rejection_reason',
            ]);
        });
    }
};
