<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Team/group items (participant_type in group, team, pair, trio) get ONE
 * chest number for the whole squad, not one per member. Chest numbers on
 * fest_participants carry a unique (event_id, chest_no) index, so a squad's
 * shared number can't live there without every member colliding on it —
 * it needs its own column on fest_groups instead.
 *
 * fest_groups doesn't carry event_id today (only registration_id); add it
 * (backfilled from the parent registration) so the chest-number unique
 * index can be scoped the same way fest_participants already is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_groups')) {
            return;
        }

        if (! Schema::hasColumn('fest_groups', 'event_id')) {
            Schema::table('fest_groups', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->after('registration_id');
                $table->integer('chest_no')->nullable()->after('team_name');
                $table->timestamp('chest_revealed_at')->nullable()->after('chest_no');
            });

            DB::statement(<<<'SQL'
                UPDATE fest_groups
                SET event_id = (
                    SELECT fest_registrations.event_id
                    FROM fest_registrations
                    WHERE fest_registrations.id = fest_groups.registration_id
                )
            SQL);
        }

        if (! Schema::hasIndex('fest_groups', 'fest_groups_event_chest_unique')) {
            Schema::table('fest_groups', function (Blueprint $table) {
                $table->unique(['event_id', 'chest_no'], 'fest_groups_event_chest_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_groups')) {
            return;
        }

        if (Schema::hasIndex('fest_groups', 'fest_groups_event_chest_unique')) {
            Schema::table('fest_groups', function (Blueprint $table) {
                $table->dropUnique('fest_groups_event_chest_unique');
            });
        }

        if (Schema::hasColumn('fest_groups', 'chest_revealed_at')) {
            Schema::table('fest_groups', function (Blueprint $table) {
                $table->dropColumn(['event_id', 'chest_no', 'chest_revealed_at']);
            });
        }
    }
};
