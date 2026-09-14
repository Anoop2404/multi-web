<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index audit, 2026-09-14 — public fest pages (scoreboard, results, TV, item finder,
 * search) run these exact filter shapes on nearly every anonymous page load. Postgres
 * does not auto-index foreign key columns the way MySQL does, so a plain
 * `$table->foreignId('x')->constrained()` carries no index unless one was added
 * explicitly — every column below was genuinely unindexed before this migration.
 *
 * 1. fest_marks(event_id, item_id) — EventContext::scoreboardByCategory()/
 *    scoreboardByPhase()/recalculateSchoolPoints(), PublicFestScoreboardService::
 *    scoreboard()/provisionalScoreboard(), and FestPortalController::show()/results()/
 *    schoolResultsRoster()/tv() all filter FestMark by event_id (sometimes plus
 *    item_id). The table's only prior index, unique(item_id, participant_id), doesn't
 *    have event_id as a leftmost column, so none of those queries could use it.
 * 2. fest_events(parent_event_id, source_phase_id) — the phase/cumulative-scoreboard
 *    stack (FestPhaseScoreboardService::phaseScoreboard()/phaseScoreboardForRegion(),
 *    FestCumulativeChampionshipService::phaseLeaves(), PublicFestScoreboardService::
 *    scopes()) filters this pair verbatim, and it's exercised on every public page for
 *    a phased event. Neither column had ever been indexed.
 * 3. fest_event_items(event_id, is_enabled) — the event landing page and item finder
 *    (FestPortalController::show()/itemFinder(), EventContext::scoreboardCategories())
 *    scan every enabled item for an event on nearly every visit. fest_event_items had
 *    no index at all beyond its primary key.
 * 4. fest_participants(chest_no), fest_participants(level_registration_number) — the
 *    public search box (FestPortalController::search()) OR's these two columns for any
 *    numeric query. chest_no sits third in the existing (event_id, chest_head_id,
 *    chest_no) unique index, unreachable without event_id in the same WHERE, and
 *    level_registration_number had never been indexed.
 * 5. fest_participants(teacher_id) — mirrors the existing student_id index for the same
 *    "this event's other items for this participant" lookup
 *    (FestPublicVisibilityService::publicParticipantItems()), used on teacher-fest
 *    participant pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('fest_marks', ['event_id', 'item_id'], 'fest_marks_event_item_idx');
        $this->addIndex('fest_events', ['parent_event_id', 'source_phase_id'], 'fest_events_parent_phase_idx');
        $this->addIndex('fest_event_items', ['event_id', 'is_enabled'], 'fest_event_items_event_enabled_idx');
        $this->addIndex('fest_participants', ['chest_no'], 'fest_participants_chest_no_idx');
        $this->addIndex('fest_participants', ['level_registration_number'], 'fest_participants_level_reg_no_idx');
        $this->addIndex('fest_participants', ['teacher_id'], 'fest_participants_teacher_id_idx');
    }

    public function down(): void
    {
        $this->dropIndex('fest_marks', 'fest_marks_event_item_idx');
        $this->dropIndex('fest_events', 'fest_events_parent_phase_idx');
        $this->dropIndex('fest_event_items', 'fest_event_items_event_enabled_idx');
        $this->dropIndex('fest_participants', 'fest_participants_chest_no_idx');
        $this->dropIndex('fest_participants', 'fest_participants_level_reg_no_idx');
        $this->dropIndex('fest_participants', 'fest_participants_teacher_id_idx');
    }

    /** @param  list<string>  $columns */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }
};
