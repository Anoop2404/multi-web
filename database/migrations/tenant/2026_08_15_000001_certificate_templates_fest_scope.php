<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('certificate_templates')) {
            Schema::table('certificate_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('certificate_templates', 'event_id')) {
                    $table->unsignedBigInteger('event_id')->nullable()->after('event_type');
                    $table->foreign('event_id')->references('id')->on('fest_events')->nullOnDelete();
                    $table->index('event_id');
                }
                if (! Schema::hasColumn('certificate_templates', 'item_id')) {
                    $table->unsignedBigInteger('item_id')->nullable()->after('event_id');
                    $table->foreign('item_id')->references('id')->on('fest_event_items')->nullOnDelete();
                    $table->index('item_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificate_templates')) {
            Schema::table('certificate_templates', function (Blueprint $table) {
                if (Schema::hasColumn('certificate_templates', 'item_id')) {
                    $table->dropForeign(['item_id']);
                    $table->dropIndex(['item_id']);
                    $table->dropColumn('item_id');
                }
                if (Schema::hasColumn('certificate_templates', 'event_id')) {
                    $table->dropForeign(['event_id']);
                    $table->dropIndex(['event_id']);
                    $table->dropColumn('event_id');
                }
            });
        }
    }
};
