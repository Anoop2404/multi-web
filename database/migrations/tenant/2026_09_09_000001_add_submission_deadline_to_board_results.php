<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_results', function (Blueprint $table) {
            $table->timestamp('submission_deadline')->nullable()->after('submission_count');
        });
    }

    public function down(): void
    {
        Schema::table('board_results', function (Blueprint $table) {
            $table->dropColumn('submission_deadline');
        });
    }
};
