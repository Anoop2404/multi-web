<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->string('timing_mode', 20)->default('per_participant')->after('duration_minutes');
            $table->unsignedSmallInteger('calling_buffer_minutes')->nullable()->after('timing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->dropColumn(['timing_mode', 'calling_buffer_minutes']);
        });
    }
};
