<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_groups', function (Blueprint $table) {
            if (Schema::hasColumn('fest_groups', 'chest_is_manual')) {
                $table->dropColumn('chest_is_manual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_groups', function (Blueprint $table) {
            $table->boolean('chest_is_manual')->default(false)->after('chest_no');
        });
    }
};
