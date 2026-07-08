<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'conduct_mode')) {
                $table->string('conduct_mode', 20)->default('standard')->after('level_round');
            }
            if (! Schema::hasColumn('fest_events', 'partition_role')) {
                $table->string('partition_role', 32)->nullable()->after('cluster_label');
            }
            if (! Schema::hasColumn('fest_events', 'partition_key')) {
                $table->string('partition_key', 64)->nullable()->after('partition_role');
            }
            if (! Schema::hasColumn('fest_events', 'aggregation_config')) {
                $table->json('aggregation_config')->nullable()->after('partition_key');
            }
            if (! Schema::hasColumn('fest_events', 'scoring_preset')) {
                $table->string('scoring_preset', 64)->nullable()->after('aggregation_config');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            foreach (['scoring_preset', 'aggregation_config', 'partition_key', 'partition_role', 'conduct_mode'] as $col) {
                if (Schema::hasColumn('fest_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
