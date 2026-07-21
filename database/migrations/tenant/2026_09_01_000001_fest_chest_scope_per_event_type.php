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
            // so chest numbers are unique per item participant rather than event-wide.
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

            // 2. Drop single event-wide unique index on fest_participants if present
            if (Schema::hasIndex('fest_participants', 'fest_participants_event_chest_unique')) {
                Schema::table('fest_participants', function (Blueprint $table) {
                    $table->dropUnique('fest_participants_event_chest_unique');
                });
            }

            // 3. Add composite unique index on (event_id, chest_head_id, chest_no)
            // This allows chest numbers to be per-item for non-sports events while remaining per-event (chest_head_id=0) for sports.
            if (Schema::hasColumn('fest_participants', 'chest_head_id')
                && ! Schema::hasIndex('fest_participants', 'fest_participants_event_head_chest_unique')) {
                Schema::table('fest_participants', function (Blueprint $table) {
                    $table->unique(
                        ['event_id', 'chest_head_id', 'chest_no'],
                        'fest_participants_event_head_chest_unique'
                    );
                });
            }
        }

        // 4. Drop event-wide unique index on fest_groups so group/team items also support per-item chest numbering
        if (Schema::hasTable('fest_groups') && Schema::hasIndex('fest_groups', 'fest_groups_event_chest_unique')) {
            Schema::table('fest_groups', function (Blueprint $table) {
                $table->dropUnique('fest_groups_event_chest_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_groups') && ! Schema::hasIndex('fest_groups', 'fest_groups_event_chest_unique')) {
            Schema::table('fest_groups', function (Blueprint $table) {
                $table->unique(['event_id', 'chest_no'], 'fest_groups_event_chest_unique');
            });
        }

        if (! Schema::hasTable('fest_participants')) {
            return;
        }

        if (Schema::hasIndex('fest_participants', 'fest_participants_event_head_chest_unique')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->dropUnique('fest_participants_event_head_chest_unique');
            });
        }

        if (! Schema::hasIndex('fest_participants', 'fest_participants_event_chest_unique')
            && Schema::hasColumn('fest_participants', 'event_id')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->unique(['event_id', 'chest_no'], 'fest_participants_event_chest_unique');
            });
        }
    }
};
