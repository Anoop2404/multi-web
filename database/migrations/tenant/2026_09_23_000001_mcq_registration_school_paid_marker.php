<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School-side "mark paid" checklist for MCQ registrations.
 *
 * This is a school-internal flag only — it lets a School Admin cross-verify
 * their physical/offline payment collection against the registered roster,
 * per class, before uploading batch payment proof for Sahodaya approval.
 * It is intentionally independent of FeeReceipt / McqSchoolFee, which remain
 * the source of truth for the actual Sahodaya-approved payment workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_registrations')) {
            return;
        }

        Schema::table('mcq_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_registrations', 'school_marked_paid_at')) {
                $table->timestamp('school_marked_paid_at')->nullable()->after('cancelled_by_user_id');
            }
            if (! Schema::hasColumn('mcq_registrations', 'school_marked_paid_by_user_id')) {
                $table->unsignedBigInteger('school_marked_paid_by_user_id')->nullable()->after('school_marked_paid_at');
            }
            if (! Schema::hasColumn('mcq_registrations', 'school_paid_note')) {
                $table->string('school_paid_note', 255)->nullable()->after('school_marked_paid_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mcq_registrations')) {
            return;
        }

        Schema::table('mcq_registrations', function (Blueprint $table) {
            foreach (['school_paid_note', 'school_marked_paid_by_user_id', 'school_marked_paid_at'] as $col) {
                if (Schema::hasColumn('mcq_registrations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
