<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_state_programs', function (Blueprint $table) {
            $table->json('level_event_settings')->nullable()->after('level_policies');
            $table->unsignedInteger('settings_version')->default(1)->after('level_event_settings');
        });

        Schema::table('fest_state_program_items', function (Blueprint $table) {
            $table->string('advancement_mode', 32)->default('normal')->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('fest_state_programs', function (Blueprint $table) {
            $table->dropColumn(['level_event_settings', 'settings_version']);
        });

        Schema::table('fest_state_program_items', function (Blueprint $table) {
            $table->dropColumn('advancement_mode');
        });
    }
};
