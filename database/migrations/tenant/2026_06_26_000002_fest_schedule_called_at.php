<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_schedules', function (Blueprint $table) {
            $table->timestamp('called_at')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('fest_schedules', function (Blueprint $table) {
            $table->dropColumn('called_at');
        });
    }
};
