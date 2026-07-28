<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topper_count_configs', function (Blueprint $table) {
            $table->string('rank_style', 20)->default('competition')->after('tie_mode');
        });
    }

    public function down(): void
    {
        Schema::table('topper_count_configs', function (Blueprint $table) {
            $table->dropColumn('rank_style');
        });
    }
};
