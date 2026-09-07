<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends fest_appeals (previously a pure results/rank dispute record) to also
 * carry wildcard-appeal requests: a student skipped at one tier appealing for a
 * slot at the next tier up. A wildcard appeal is submitted before any
 * FestParticipant exists for the target item, so participant_id must become
 * nullable; student_id/item_id identify who and what the wildcard targets.
 * granted_registration_id / granted_state_reference record what was actually
 * created once the appeal is approved (idempotency + certificate/result linkage).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_appeals')) {
            return;
        }

        Schema::table('fest_appeals', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_appeals', 'appeal_type')) {
                $table->string('appeal_type', 30)->default('dispute')->after('event_id');
            }

            if (! Schema::hasColumn('fest_appeals', 'student_id')) {
                $table->unsignedBigInteger('student_id')->nullable()->after('participant_id');
                $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            }

            if (! Schema::hasColumn('fest_appeals', 'item_id')) {
                $table->unsignedBigInteger('item_id')->nullable()->after('student_id');
                $table->foreign('item_id')->references('id')->on('fest_event_items')->nullOnDelete();
            }

            if (! Schema::hasColumn('fest_appeals', 'granted_registration_id')) {
                $table->unsignedBigInteger('granted_registration_id')->nullable()->after('resolution_note');
                $table->foreign('granted_registration_id')->references('id')->on('fest_registrations')->nullOnDelete();
            }

            if (! Schema::hasColumn('fest_appeals', 'granted_state_reference')) {
                // Cross-tenant (State runs as a separate domain reached over HTTP) — a
                // plain reference string (outbox id), not a local foreign key.
                $table->string('granted_state_reference')->nullable()->after('granted_registration_id');
            }
        });

        // participant_id was NOT NULL with a cascading FK — a wildcard appeal has no
        // participant yet, so it must become nullable. Column type/FK are unchanged.
        Schema::table('fest_appeals', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_appeals')) {
            return;
        }

        Schema::table('fest_appeals', function (Blueprint $table) {
            if (Schema::hasColumn('fest_appeals', 'granted_state_reference')) {
                $table->dropColumn('granted_state_reference');
            }
            if (Schema::hasColumn('fest_appeals', 'granted_registration_id')) {
                $table->dropForeign(['granted_registration_id']);
                $table->dropColumn('granted_registration_id');
            }
            if (Schema::hasColumn('fest_appeals', 'item_id')) {
                $table->dropForeign(['item_id']);
                $table->dropColumn('item_id');
            }
            if (Schema::hasColumn('fest_appeals', 'student_id')) {
                $table->dropForeign(['student_id']);
                $table->dropColumn('student_id');
            }
            if (Schema::hasColumn('fest_appeals', 'appeal_type')) {
                $table->dropColumn('appeal_type');
            }
        });

        Schema::table('fest_appeals', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable(false)->change();
        });
    }
};
