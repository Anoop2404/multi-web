<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->boolean('combine_regions_at_finale')->default(true)->after('conduct_mode');
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->dropColumn('combine_regions_at_finale');
        });
    }
};
