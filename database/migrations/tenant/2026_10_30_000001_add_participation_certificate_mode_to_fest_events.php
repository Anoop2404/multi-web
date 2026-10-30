<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_events') && ! Schema::hasColumn('fest_events', 'participation_certificate_mode')) {
            Schema::table('fest_events', fn (Blueprint $table) => $table->string('participation_certificate_mode', 20)->default('per_person'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'participation_certificate_mode')) {
            Schema::table('fest_events', fn (Blueprint $table) => $table->dropColumn('participation_certificate_mode'));
        }
    }
};
