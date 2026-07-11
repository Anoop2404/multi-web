<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_groups')) {
            return;
        }

        Schema::table('fest_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_groups', 'coach_name')) {
                $table->string('coach_name')->nullable()->after('team_name');
            }
            if (! Schema::hasColumn('fest_groups', 'coach_phone')) {
                $table->string('coach_phone', 40)->nullable()->after('coach_name');
            }
            if (! Schema::hasColumn('fest_groups', 'manager_name')) {
                $table->string('manager_name')->nullable()->after('coach_phone');
            }
            if (! Schema::hasColumn('fest_groups', 'manager_phone')) {
                $table->string('manager_phone', 40)->nullable()->after('manager_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_groups')) {
            return;
        }

        Schema::table('fest_groups', function (Blueprint $table) {
            foreach (['coach_name', 'coach_phone', 'manager_name', 'manager_phone'] as $col) {
                if (Schema::hasColumn('fest_groups', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
