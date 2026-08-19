<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-batch override for the per-student registration fee, additive alongside
 * school_base_fee. Previously the only per-student rate was FestEvent::
 * student_registration_fee — one flat amount for the whole event, applied
 * independently inside each batch's own fee calculation (so it already charged once
 * per batch/phase using the SAME rate everywhere) — but with no way to configure a
 * genuinely DIFFERENT amount for one phase vs another (e.g. Wayanad Sahodaya wants a
 * distinct, explicitly-set ₹250-per-student charge configured directly on each of its
 * two phase batches, not implicitly inherited from one shared event-level number).
 * Null (the default) preserves existing behavior exactly — every already-configured
 * event keeps using the event-level rate via fee_model='per_student' until a batch
 * explicitly opts into its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_registration_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_registration_batches', 'student_registration_fee')) {
                $table->decimal('student_registration_fee', 10, 2)->nullable()->after('school_base_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_registration_batches', function (Blueprint $table) {
            if (Schema::hasColumn('fest_registration_batches', 'student_registration_fee')) {
                $table->dropColumn('student_registration_fee');
            }
        });
    }
};
