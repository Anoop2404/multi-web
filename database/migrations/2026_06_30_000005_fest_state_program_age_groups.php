<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_state_program_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_state_program_items', 'age_group')) {
                $table->enum('age_group', ['u14', 'u17', 'u19', 'open'])->nullable()->after('class_group');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_state_program_items', function (Blueprint $table) {
            if (Schema::hasColumn('fest_state_program_items', 'age_group')) {
                $table->dropColumn('age_group');
            }
        });
    }
};
