<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('id_card_templates', 'grid_json')) {
                // Exact physical placement for a die-cut print sheet: {cols, rows,
                // first_col_center_mm, first_row_center_mm, col_pitch_mm, row_pitch_mm}.
                // Null = fall back to the old 2-per-row auto-flow table. See
                // IdCardTemplate::gridLayout().
                $table->json('grid_json')->nullable()->after('page_height_mm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            if (Schema::hasColumn('id_card_templates', 'grid_json')) {
                $table->dropColumn('grid_json');
            }
        });
    }
};
