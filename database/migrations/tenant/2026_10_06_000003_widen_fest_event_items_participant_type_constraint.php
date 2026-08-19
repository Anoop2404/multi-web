<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FestTaxonomyRegistry (app/Services/Events/FestTaxonomyRegistry.php) and FestTeamSquadRules::
 * MULTI_PERSON_TYPES have offered 'pair' and 'trio' as valid fest_event_items.participant_type
 * values since those were introduced, but the original enum/CHECK constraint from
 * 2026_06_22_000011_phase11_13_event_platform.php was never widened to match — only
 * individual/group/team pass it. Creating a Pair or Trio item (e.g. a "Conversation (Pair)"
 * or "Interview (Pair)" item) hits "violates check constraint
 * fest_event_items_participant_type_check" at the DB layer even though the admin UI and
 * FestEventController::storeItem() validation both accept it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE fest_event_items DROP CONSTRAINT IF EXISTS fest_event_items_participant_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE fest_event_items ADD CONSTRAINT fest_event_items_participant_type_check CHECK (
                participant_type IN ('individual', 'pair', 'trio', 'group', 'team')
            )
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE fest_event_items DROP CONSTRAINT IF EXISTS fest_event_items_participant_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE fest_event_items ADD CONSTRAINT fest_event_items_participant_type_check CHECK (
                participant_type IN ('individual', 'group', 'team')
            )
        SQL);
    }
};
