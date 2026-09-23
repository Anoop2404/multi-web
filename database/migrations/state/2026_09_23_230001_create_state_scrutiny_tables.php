<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 of the State Kalotsav module — scrutiny beyond approve/reject, and substitutions.
 *
 * Scrutiny today is binary: an entry is approved or rejected, and an intake with it. That loses the
 * outcome a State office actually needs most often — "this is wrong, fix it and send it back" — and
 * forces a scrutineer to reject a whole package over one missing date of birth, which a Sahodaya
 * then cannot correct because a rejected intake is closed.
 *
 * So: returned and documents_requested as first-class outcomes, each carrying the note that says
 * what to fix, and a review log that records every decision rather than only the last one. The log
 * is append-only — an appeal asks "who returned this, when, and what did they say", and an
 * overwritten status cannot answer that.
 *
 * Substitutions are separate from a plain edit on purpose: replacing a participant after
 * certification is a decision with a deadline, evidence and an approver, not a correction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_qualifier_entries', function (Blueprint $table) {
            // The note the Sahodaya reads: why this was returned, or what document is wanted.
            $table->text('review_note')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('review_note');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('reviewed_at');
            // A reserve is an entry the Sahodaya nominated as a standby; it only becomes a real
            // participant if the State accepts it in place of another.
            $table->boolean('is_reserve')->default(false)->after('reviewed_by_user_id');
        });

        Schema::create('state_entry_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('intake_id');
            $table->unsignedBigInteger('entry_id')->nullable();
            $table->uuid('state_id')->nullable()->index();
            // approved | rejected | returned | documents_requested | reopened
            $table->string('decision', 30);
            $table->string('previous_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['intake_id', 'entry_id']);
        });

        Schema::create('state_substitutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('state_event_id');
            $table->uuid('state_id')->nullable()->index();
            $table->uuid('sahodaya_id')->nullable()->index();

            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('original_participant_id')->nullable();
            $table->string('original_name');
            $table->string('substitute_name');
            $table->string('substitute_class')->nullable();
            // The School both come from — a substitution stays inside the Sahodaya that sent it, and
            // the School is carried because the State never drops it.
            $table->string('school_id')->nullable();
            $table->string('school_name')->nullable();
            $table->string('item_code', 64)->nullable();

            $table->text('reason');
            $table->string('evidence_path')->nullable();
            // requested | approved | rejected
            $table->string('status', 20)->default('requested');
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['state_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_substitutions');
        Schema::dropIfExists('state_entry_reviews');

        Schema::table('state_qualifier_entries', function (Blueprint $table) {
            $table->dropColumn(['review_note', 'reviewed_at', 'reviewed_by_user_id', 'is_reserve']);
        });
    }
};
