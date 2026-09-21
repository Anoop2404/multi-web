<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hot path indexes for Fest ID Card generator and bulk reporting.
 *
 * 1. fest_registrations(event_id, status, school_id) — optimizes event-scoped ID card
 *    and participant lookups where status is filtered (e.g. approved / not rejected)
 *    and grouped/joined by school.
 * 2. fest_participants(registration_id, participant_role) — optimizes queries filtering
 *    out standby participants for active registrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('fest_registrations', ['event_id', 'status', 'school_id'], 'fest_reg_event_status_school_idx');
        $this->addIndex('fest_participants', ['registration_id', 'participant_role'], 'fest_participants_reg_role_idx');
    }

    public function down(): void
    {
        $this->dropIndex('fest_registrations', 'fest_reg_event_status_school_idx');
        $this->dropIndex('fest_participants', 'fest_participants_reg_role_idx');
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
