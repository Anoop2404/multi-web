<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_individual_championship_points', function (Blueprint $table) {
            // Points earned via pair/trio/group/team items — tracked separately from
            // `points` (individual-item-only) so a shared group result never inflates a
            // student's personal total. Used only as a tiebreak when `points` is equal.
            $table->unsignedSmallInteger('group_points')->default(0)->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('fest_individual_championship_points', function (Blueprint $table) {
            $table->dropColumn('group_points');
        });
    }
};
