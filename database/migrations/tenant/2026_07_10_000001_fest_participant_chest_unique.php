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
            if (! Schema::hasColumn('fest_participants', 'event_id')) {
                $table->unsignedBigInteger('event_id')->nullable()->after('registration_id');
            }
        });

        if (Schema::hasColumn('fest_participants', 'event_id') && Schema::hasTable('fest_registrations')) {
            DB::table('fest_participants')
                ->join('fest_registrations', 'fest_registrations.id', '=', 'fest_participants.registration_id')
                ->whereNull('fest_participants.event_id')
                ->update(['fest_participants.event_id' => DB::raw('fest_registrations.event_id')]);
        }

        if (Schema::hasColumn('fest_participants', 'event_id')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                $table->unique(['event_id', 'chest_no'], 'fest_participants_event_chest_unique');
            });
        }

        if (Schema::hasTable('fest_event_items') && ! Schema::hasColumn('fest_event_items', 'ranking_direction')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                $table->string('ranking_direction', 8)->nullable()->after('sport_discipline');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_participants')) {
            Schema::table('fest_participants', function (Blueprint $table) {
                if (Schema::hasColumn('fest_participants', 'event_id')) {
                    $table->dropUnique('fest_participants_event_chest_unique');
                    $table->dropColumn('event_id');
                }
            });
        }

        if (Schema::hasTable('fest_event_items') && Schema::hasColumn('fest_event_items', 'ranking_direction')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                $table->dropColumn('ranking_direction');
            });
        }
    }
};
