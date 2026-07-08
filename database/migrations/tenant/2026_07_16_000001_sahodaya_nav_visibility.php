<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sahodaya_profiles') && ! Schema::hasColumn('sahodaya_profiles', 'nav_visibility')) {
            Schema::table('sahodaya_profiles', function (Blueprint $table) {
                $table->json('nav_visibility')->nullable()->after('fest_class_group_scheme');
            });
        }

        if (Schema::hasTable('fest_events') && ! Schema::hasColumn('fest_events', 'nav_hidden')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->boolean('nav_hidden')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sahodaya_profiles') && Schema::hasColumn('sahodaya_profiles', 'nav_visibility')) {
            Schema::table('sahodaya_profiles', function (Blueprint $table) {
                $table->dropColumn('nav_visibility');
            });
        }

        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'nav_hidden')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('nav_hidden');
            });
        }
    }
};
