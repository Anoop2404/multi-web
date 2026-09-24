<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 of the State Kalotsav module — a judge declaring their sheet finished.
 *
 * Without this, "the panel has finished scoring" can only be guessed from counting scores, which
 * cannot tell a judge who has not started from a judge who deliberately left a competitor unscored.
 * A submitted sheet is also the point after which further edits should be visible as changes rather
 * than silent, which is why the timestamp is recorded rather than a boolean.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'state';
    }

    public function up(): void
    {
        Schema::connection('state')->table('state_judge_assignments', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('user_id');
            $table->unsignedInteger('submitted_count')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::connection('state')->table('state_judge_assignments', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'submitted_count']);
        });
    }
};
