<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_items', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('fee_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            if (Schema::hasColumn('fest_event_items', 'is_enabled')) {
                $table->dropColumn('is_enabled');
            }
        });
    }
};
