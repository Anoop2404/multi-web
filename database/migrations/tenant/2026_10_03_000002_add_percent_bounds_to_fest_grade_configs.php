<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_grade_configs')) {
            return;
        }

        Schema::table('fest_grade_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_grade_configs', 'min_percent')) {
                $table->decimal('min_percent', 5, 2)->nullable()->after('max_score');
            }
            if (! Schema::hasColumn('fest_grade_configs', 'max_percent')) {
                $table->decimal('max_percent', 5, 2)->nullable()->after('min_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_grade_configs', function (Blueprint $table) {
            if (Schema::hasColumn('fest_grade_configs', 'max_percent')) {
                $table->dropColumn('max_percent');
            }
            if (Schema::hasColumn('fest_grade_configs', 'min_percent')) {
                $table->dropColumn('min_percent');
            }
        });
    }
};
