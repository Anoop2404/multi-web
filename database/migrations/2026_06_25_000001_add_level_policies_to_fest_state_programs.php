<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_state_programs', function (Blueprint $table) {
            $table->json('level_policies')->nullable()->after('level_fees');
        });
    }

    public function down(): void
    {
        Schema::table('fest_state_programs', function (Blueprint $table) {
            $table->dropColumn('level_policies');
        });
    }
};
