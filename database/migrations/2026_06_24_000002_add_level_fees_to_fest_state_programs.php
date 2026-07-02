<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_state_programs', function (Blueprint $table) {
            $table->json('level_fees')->nullable()->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('fest_state_programs', function (Blueprint $table) {
            $table->dropColumn('level_fees');
        });
    }
};
