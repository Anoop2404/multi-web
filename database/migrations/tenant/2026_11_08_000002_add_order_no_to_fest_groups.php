<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_groups', function (Blueprint $table) {
            // Same field as fest_participants.order_no, for team/group items where the
            // Mark Entry display order is shared by the whole squad rather than set per
            // member -- mirrors how chest_no already works for these items.
            $table->unsignedSmallInteger('order_no')->nullable()->after('chest_no');
        });
    }

    public function down(): void
    {
        Schema::table('fest_groups', function (Blueprint $table) {
            $table->dropColumn('order_no');
        });
    }
};
