<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('id_card_templates', 'page_width_mm')) {
                $table->decimal('page_width_mm', 7, 2)->nullable()->after('cards_per_page');
            }
            if (! Schema::hasColumn('id_card_templates', 'page_height_mm')) {
                $table->decimal('page_height_mm', 7, 2)->nullable()->after('page_width_mm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->dropColumn(['page_width_mm', 'page_height_mm']);
        });
    }
};
