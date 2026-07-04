<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'portal_welcome_seen')) {
                $table->boolean('portal_welcome_seen')->default(false)->after('must_change_password');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'school_setup_wizard_dismissed')) {
                $table->boolean('school_setup_wizard_dismissed')->default(false)->after('prefixes_locked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'portal_welcome_seen')) {
                $table->dropColumn('portal_welcome_seen');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'school_setup_wizard_dismissed')) {
                $table->dropColumn('school_setup_wizard_dismissed');
            }
        });
    }
};
