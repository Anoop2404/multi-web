<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the state-admin data-isolation gap: PlatformUser (state_admin/state_staff
 * accounts) and the central tables they operate on had no notion of "which state"
 * at all — only a role name — so any state admin could read/write every other
 * state's fest programs and remittances. See FRD-13 gap analysis, Finding A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('state_id')->nullable()->after('tenant_id');
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
        });

        Schema::table('fest_state_programs', function (Blueprint $table) {
            $table->uuid('state_id')->nullable()->after('id');
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
        });

        Schema::table('state_remittances', function (Blueprint $table) {
            $table->uuid('state_id')->nullable()->after('id');
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('state_remittances', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
        });

        Schema::table('fest_state_programs', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
        });
    }
};
