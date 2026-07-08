<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_participants')) {
            return;
        }

        Schema::table('fest_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_participants', 'chest_head_id')) {
                $table->unsignedBigInteger('chest_head_id')->default(0)->after('event_id');
            }
        });

        if (Schema::hasColumn('fest_participants', 'chest_head_id')
            && Schema::hasTable('fest_registrations')
            && Schema::hasTable('fest_event_items')) {
            DB::statement('
                UPDATE fest_participants
                SET chest_head_id = COALESCE(fest_event_items.head_id, 0)
                FROM fest_registrations
                INNER JOIN fest_event_items ON fest_event_items.id = fest_registrations.item_id
                WHERE fest_registrations.id = fest_participants.registration_id
                  AND fest_participants.chest_no IS NOT NULL
            ');
        }

        if (Schema::hasIndex('fest_participants', 'fest_participants_event_chest_unique')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->dropUnique('fest_participants_event_chest_unique');
            });
        }

        if (Schema::hasColumn('fest_participants', 'chest_head_id')
            && ! Schema::hasIndex('fest_participants', 'fest_participants_event_head_chest_unique')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->unique(
                    ['event_id', 'chest_head_id', 'chest_no'],
                    'fest_participants_event_head_chest_unique',
                );
            });
        }
    }

    public function down(): void
    {
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

        if (Schema::hasColumn('fest_participants', 'chest_head_id')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->dropColumn('chest_head_id');
            });
        }
    }
};
