<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_groups', function (Blueprint $table) {
            // Same flag as fest_participants.chest_is_manual, for team/group items where
            // the chest number lives on the squad rather than the individual member.
            $table->boolean('chest_is_manual')->default(false)->after('chest_no');
        });
    }

    public function down(): void
    {
        Schema::table('fest_groups', function (Blueprint $table) {
            $table->dropColumn('chest_is_manual');
        });
    }
};
