<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_region_change_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('phase_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Event-level requests must be retained; do not discard them during rollback.
    }
};
