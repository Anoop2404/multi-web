<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_state_program_propagations') && ! Schema::hasColumn('fest_state_program_propagations', 'is_enabled')) {
            Schema::table('fest_state_program_propagations', function (Blueprint $table) {
                $table->boolean('is_enabled')->default(true)->after('tenant_event_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_state_program_propagations') && Schema::hasColumn('fest_state_program_propagations', 'is_enabled')) {
            Schema::table('fest_state_program_propagations', function (Blueprint $table) {
                $table->dropColumn('is_enabled');
            });
        }
    }
};
