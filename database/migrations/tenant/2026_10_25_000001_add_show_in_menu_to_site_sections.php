<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_sections', function (Blueprint $table) {
            if (! Schema::hasColumn('site_sections', 'show_in_menu')) {
                $table->boolean('show_in_menu')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_sections', function (Blueprint $table) {
            if (Schema::hasColumn('site_sections', 'show_in_menu')) {
                $table->dropColumn('show_in_menu');
            }
        });
    }
};
