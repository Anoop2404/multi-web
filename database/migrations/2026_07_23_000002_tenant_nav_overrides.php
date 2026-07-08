<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants') && ! Schema::hasColumn('tenants', 'nav_overrides')) {
            Schema::table('tenants', function (Blueprint $table) {
                // Platform (super admin) hard cap on sidebar menus/programs for a Sahodaya.
                $table->json('nav_overrides')->nullable()->after('application_payload');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'nav_overrides')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('nav_overrides');
            });
        }
    }
};
