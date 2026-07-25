<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_results', function (Blueprint $table) {
            // Common "out of" marks for this class + academic year's toppers (e.g. 500).
            // Remembered on the result so the bulk topper-entry grid doesn't need it re-typed.
            $table->unsignedInteger('total_marks')->nullable()->after('average_mark');
        });
    }

    public function down(): void
    {
        Schema::table('board_results', function (Blueprint $table) {
            $table->dropColumn('total_marks');
        });
    }
};
