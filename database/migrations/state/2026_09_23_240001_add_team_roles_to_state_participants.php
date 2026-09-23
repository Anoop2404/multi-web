<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 of the State Kalotsav module — team composition.
 *
 * A team entry is a registration with several participants; what was missing is which of them is
 * which. A group item needs a leader to report to the stage and to sign for the team, and standbys
 * who travel but do not compete unless someone is substituted in. Without the distinction a standby
 * is indistinguishable from a competitor, which breaks both the team-size check and the chest-number
 * allocation that Phase 5 will do.
 *
 * withdrawn_at, rather than deleting the row: a participant replaced mid-event is part of the
 * record, and the substitution that replaced them points at them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_fest_participants', function (Blueprint $table) {
            $table->boolean('is_leader')->default(false)->after('class_name');
            // A standby travels with the team but does not count toward the team size until they
            // are substituted in.
            $table->boolean('is_standby')->default(false)->after('is_leader');
            $table->timestamp('withdrawn_at')->nullable()->after('is_standby');
        });
    }

    public function down(): void
    {
        Schema::table('state_fest_participants', function (Blueprint $table) {
            $table->dropColumn(['is_leader', 'is_standby', 'withdrawn_at']);
        });
    }
};
