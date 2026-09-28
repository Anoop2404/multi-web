<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_participation_policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_pair_per_student')->nullable()->after('max_group_per_student');
            $table->unsignedSmallInteger('max_common_per_student')->nullable()->after('max_pair_per_student');
            $table->unsignedSmallInteger('max_overall_per_student')->nullable()->after('max_total_per_student');
            $table->string('pair_points_mode', 20)->default('group')->after('max_overall_per_student');
            $table->json('combo_profiles')->nullable()->after('pair_points_mode');
            $table->json('rules_config')->nullable()->after('combo_profiles');
        });
    }

    public function down(): void
    {
        Schema::table('fest_participation_policies', function (Blueprint $table) {
            $table->dropColumn([
                'max_pair_per_student',
                'max_common_per_student',
                'max_overall_per_student',
                'pair_points_mode',
                'combo_profiles',
                'rules_config',
            ]);
        });
    }
};
