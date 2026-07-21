<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_participants')) {
            // 1. Update chest_head_id to item_id for non-sports events (custom, fest, kalolsavam)
            if (Schema::hasColumn('fest_participants', 'chest_head_id')
                && Schema::hasTable('fest_registrations')
                && Schema::hasTable('fest_events')) {
                DB::statement("
                    UPDATE fest_participants
                    SET chest_head_id = fest_registrations.item_id
                    FROM fest_registrations
                    INNER JOIN fest_events ON fest_events.id = fest_registrations.event_id
                    WHERE fest_registrations.id = fest_participants.registration_id
                      AND fest_events.event_type != 'sports'
                      AND fest_participants.chest_head_id = 0
                ");
            }

            // 2. Drop single event-wide unique constraint and index on fest_participants in PostgreSQL / MySQL
            try {
                DB::statement('ALTER TABLE fest_participants DROP CONSTRAINT IF EXISTS fest_participants_event_chest_unique');
            } catch (\Throwable $e) {
            }

            try {
                DB::statement('DROP INDEX IF EXISTS fest_participants_event_chest_unique');
            } catch (\Throwable $e) {
            }

            // 3. Add composite unique index on (event_id, chest_head_id, chest_no)
            if (! Schema::hasIndex('fest_participants', 'fest_participants_event_head_chest_unique')) {
                try {
                    Schema::table('fest_participants', function (Blueprint $table) {
                        $table->unique(
                            ['event_id', 'chest_head_id', 'chest_no'],
                            'fest_participants_event_head_chest_unique'
                        );
                    });
                } catch (\Throwable $e) {
                }
            }
        }

        // 4. Drop event-wide unique constraint and index on fest_groups in PostgreSQL / MySQL
        if (Schema::hasTable('fest_groups')) {
            try {
                DB::statement('ALTER TABLE fest_groups DROP CONSTRAINT IF EXISTS fest_groups_event_chest_unique');
            } catch (\Throwable $e) {
            }

            try {
                DB::statement('DROP INDEX IF EXISTS fest_groups_event_chest_unique');
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_groups')) {
            try {
                Schema::table('fest_groups', function (Blueprint $table) {
                    $table->unique(['event_id', 'chest_no'], 'fest_groups_event_chest_unique');
                });
            } catch (\Throwable $e) {
            }
        }

        if (Schema::hasTable('fest_participants')) {
            try {
                DB::statement('ALTER TABLE fest_participants DROP CONSTRAINT IF EXISTS fest_participants_event_head_chest_unique');
            } catch (\Throwable $e) {
            }
        }
    }
};
