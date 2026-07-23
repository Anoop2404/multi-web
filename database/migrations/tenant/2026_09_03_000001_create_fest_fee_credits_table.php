<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive, opt-in ledger for the "money paid for an item that was later rejected"
     * gap: FestSchoolEventFeeService::recalculate() already shrinks total_due when a
     * registration is rejected, but amount_paid was never adjusted, leaving the school
     * silently overpaid with no record of it. This table records that delta so it can be
     * surfaced to the school and applied to a future fee — see FestRegistrationBulkService::
     * rejectMany() for where rows get created, and docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md
     * §9.2 for the full design (including why this is a new table rather than reusing
     * FeeReceiptReversalService, which reverses a whole receipt at once).
     */
    public function up(): void
    {
        if (Schema::hasTable('fest_fee_credits')) {
            return;
        }

        Schema::create('fest_fee_credits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('fest_school_event_fee_id');
            $table->foreign('fest_school_event_fee_id')
                ->references('id')->on('fest_school_event_fees')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('source_registration_id')->nullable();
            $table->foreign('source_registration_id')
                ->references('id')->on('fest_registrations')
                ->nullOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('reason')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // Set once a future recalculate()/admin action absorbs this credit against a
            // new balance. Null = still outstanding credit owed to the school.
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            $table->index('fest_school_event_fee_id');
            $table->index(['fest_school_event_fee_id', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_fee_credits');
    }
};
