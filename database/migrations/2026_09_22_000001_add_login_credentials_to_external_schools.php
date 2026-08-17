<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the shared access-code as the primary credential for an ExternalSchool with a
 * real username/password, resettable/viewable by State Admin — see Phase 3 of the Sahodaya
 * admin credentials plan. `access_code` is kept for the legacy-link fallback login route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_schools', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('access_code');
            $table->string('plain_password')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('external_schools', function (Blueprint $table) {
            $table->dropColumn(['username', 'password', 'plain_password']);
        });
    }
};
