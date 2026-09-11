<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * fest_part_policy_event_class_unique (on event_id, class_group) never actually protected
 * the "default" policy row (class_group = NULL, the one the Settings > Participation tab
 * saves for every event) — Postgres treats every NULL as distinct for uniqueness purposes,
 * so nothing stopped two duplicate (event_id, NULL) rows from existing at once. Once that
 * happened (e.g. a double-click, or two overlapping saves), FestParticipationPolicyController
 * ::store()'s updateOrCreate() and FestEventSettingsController's ->first() read could each
 * land on a DIFFERENT one of the duplicates — an admin unchecking "Require fee approval
 * before registration approval" and saving would see it stick immediately, then find it
 * reverted to checked on the next page load. Confirmed live on a production event.
 *
 * This migration (a) keeps the most-recently-updated row per duplicate (event_id, NULL)
 * group and deletes the rest — the newest row is the admin's last actual intent — then
 * (b) adds a partial unique index covering exactly the NULL case, which Postgres DOES
 * enforce, closing the gap for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_participation_policies')) {
            return;
        }

        $duplicateEventIds = DB::table('fest_participation_policies')
            ->whereNull('class_group')
            ->select('event_id')
            ->groupBy('event_id')
            ->havingRaw('count(*) > 1')
            ->pluck('event_id');

        foreach ($duplicateEventIds as $eventId) {
            $keepId = DB::table('fest_participation_policies')
                ->where('event_id', $eventId)
                ->whereNull('class_group')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('fest_participation_policies')
                ->where('event_id', $eventId)
                ->whereNull('class_group')
                ->where('id', '!=', $keepId)
                ->delete();
        }

        // Partial unique index — the plain composite unique() on (event_id, class_group)
        // added by the original migration cannot cover this, since Postgres never treats
        // two NULLs as equal for uniqueness.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS fest_part_policy_event_null_class_unique '.
            'ON fest_participation_policies (event_id) WHERE class_group IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fest_part_policy_event_null_class_unique');
    }
};
