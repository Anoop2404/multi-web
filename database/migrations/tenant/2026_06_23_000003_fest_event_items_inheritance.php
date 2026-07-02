<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_items', 'state_program_item_id')) {
                $table->uuid('state_program_item_id')->nullable()->after('owner_level');
            }
            if (! Schema::hasColumn('fest_event_items', 'inherited_from_item_id')) {
                $table->unsignedBigInteger('inherited_from_item_id')->nullable()->after('state_program_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            foreach (['state_program_item_id', 'inherited_from_item_id'] as $col) {
                if (Schema::hasColumn('fest_event_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
