<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phases 7 and 9 of the State Kalotsav module — appeals, and certificates.
 *
 * The two belong in one migration because they are the same story from opposite ends: an appeal is
 * how a published result changes, and a changed result is what makes a certificate wrong. A
 * certificate that says second place after an appeal moved the competitor to first is worse than no
 * certificate at all, so every certificate records the result it was printed from and is marked
 * stale when that result moves.
 *
 * Certificate numbers are allocated once and never reused, including for a regenerated certificate:
 * the number is the thing a verification page is asked about, and two documents sharing one would
 * make verification meaningless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_appeals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();
            $table->uuid('sahodaya_id')->nullable()->index();

            $table->uuid('item_id')->nullable();
            $table->string('item_code', 64)->nullable();
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('participant_name')->nullable();
            // Carried, never looked up later: the State keeps its own record of who appealed from
            // where, and a School renamed afterwards must not rewrite the appeal.
            $table->string('school_name')->nullable();

            $table->text('grounds');
            $table->string('evidence_path')->nullable();
            $table->decimal('fee_amount', 10, 2)->default(0);
            // paid | waived | refunded | forfeited — an upheld appeal usually refunds the fee and a
            // dismissed one forfeits it, which is a financial outcome the ledger needs.
            $table->string('fee_status', 20)->default('paid');

            // submitted | under_review | upheld | dismissed | withdrawn
            $table->string('status', 20)->default('submitted');
            $table->text('review_notes')->nullable();
            $table->string('outcome_summary')->nullable();

            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['state_event_id', 'status']);
        });

        Schema::create('state_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();

            // merit | participation | championship | judge | official | volunteer | appreciation
            $table->string('type', 30);
            $table->string('certificate_number', 40)->unique();
            $table->string('verification_code', 64)->unique();

            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->uuid('sahodaya_id')->nullable()->index();
            // Every certificate carries both names, because that is what is printed on it and what a
            // verification page has to be able to show years later.
            $table->string('sahodaya_name')->nullable();
            $table->string('school_name')->nullable();
            $table->string('recipient_name');
            $table->uuid('item_id')->nullable();
            $table->string('item_code', 64)->nullable();
            $table->string('item_name')->nullable();
            $table->unsignedTinyInteger('position')->nullable();
            $table->string('grade', 8)->nullable();

            // The fingerprint of the result this was printed from. When the result moves, this no
            // longer matches and the certificate is stale.
            $table->string('source_fingerprint', 64)->nullable();
            $table->string('status', 20)->default('generated'); // generated | stale | superseded
            $table->uuid('batch_id')->nullable()->index();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['state_event_id', 'type', 'status']);
            $table->index(['state_event_id', 'item_id']);
        });

        Schema::create('state_certificate_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();
            $table->string('type', 30);
            $table->string('scope')->nullable();
            // queued | running | completed | failed
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('requested_count')->default(0);
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['state_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_certificate_batches');
        Schema::dropIfExists('state_certificates');
        Schema::dropIfExists('state_appeals');
    }
};
