<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_venues')) {
            Schema::table('fest_venues', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_venues', 'region_id')) {
                    $table->foreignId('region_id')->nullable()->after('event_id')->constrained('regions')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('fest_event_staff')) {
            Schema::table('fest_event_staff', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_event_staff', 'region_id')) {
                    $table->foreignId('region_id')->nullable()->after('venue_id')->constrained('regions')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_venues')) {
            Schema::table('fest_venues', function (Blueprint $table) {
                if (Schema::hasColumn('fest_venues', 'region_id')) {
                    $table->dropForeign(['region_id']);
                    $table->dropColumn('region_id');
                }
            });
        }

        if (Schema::hasTable('fest_event_staff')) {
            Schema::table('fest_event_staff', function (Blueprint $table) {
                if (Schema::hasColumn('fest_event_staff', 'region_id')) {
                    $table->dropForeign(['region_id']);
                    $table->dropColumn('region_id');
                }
            });
        }
    }
};
