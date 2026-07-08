<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_item_heads')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_item_heads', 'reg_start')) {
                    $table->date('reg_start')->nullable()->after('sort_order');
                }
                if (! Schema::hasColumn('fest_item_heads', 'reg_end')) {
                    $table->date('reg_end')->nullable()->after('reg_start');
                }
                if (! Schema::hasColumn('fest_item_heads', 'competition_start')) {
                    $table->date('competition_start')->nullable()->after('reg_end');
                }
                if (! Schema::hasColumn('fest_item_heads', 'competition_end')) {
                    $table->date('competition_end')->nullable()->after('competition_start');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_item_heads')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                foreach (['reg_start', 'reg_end', 'competition_start', 'competition_end'] as $col) {
                    if (Schema::hasColumn('fest_item_heads', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
