<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Force drop event-wide unique constraints on fest_groups and fest_participants in PostgreSQL/MySQL
        try {
            DB::statement('ALTER TABLE fest_groups DROP CONSTRAINT IF EXISTS fest_groups_event_chest_unique');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('DROP INDEX IF EXISTS fest_groups_event_chest_unique');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('ALTER TABLE fest_participants DROP CONSTRAINT IF EXISTS fest_participants_event_chest_unique');
        } catch (\Throwable $e) {
        }

        try {
            DB::statement('DROP INDEX IF EXISTS fest_participants_event_chest_unique');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
};
