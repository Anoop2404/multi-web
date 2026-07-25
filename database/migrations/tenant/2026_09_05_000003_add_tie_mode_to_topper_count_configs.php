<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topper_count_configs', function (Blueprint $table) {
            // include_group: keep the full tie group at the cutoff rank (list may exceed top_n).
            // hard_cap: truncate to exactly top_n, breaking ties deterministically.
            $table->string('tie_mode', 20)->default('include_group')->after('top_n');
        });
    }

    public function down(): void
    {
        Schema::table('topper_count_configs', function (Blueprint $table) {
            $table->dropColumn('tie_mode');
        });
    }
};
