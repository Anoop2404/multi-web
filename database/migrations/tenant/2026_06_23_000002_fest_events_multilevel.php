<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'conduct_levels')) {
                $table->json('conduct_levels')->nullable()->after('conductor_level');
            }
            if (! Schema::hasColumn('fest_events', 'level_round')) {
                $table->enum('level_round', ['state', 'sahodaya', 'school'])->default('sahodaya')->after('conduct_levels');
            }
            if (! Schema::hasColumn('fest_events', 'state_program_id')) {
                $table->uuid('state_program_id')->nullable()->after('level_round');
            }
            if (! Schema::hasColumn('fest_events', 'conducting_school_id')) {
                $table->string('conducting_school_id')->nullable()->after('state_program_id');
            }
        });

        DB::table('fest_events')->whereNull('conduct_levels')->update([
            'conduct_levels' => json_encode(['sahodaya']),
            'level_round'    => 'sahodaya',
        ]);
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            foreach (['conduct_levels', 'level_round', 'state_program_id', 'conducting_school_id'] as $col) {
                if (Schema::hasColumn('fest_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
