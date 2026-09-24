<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md.
 *
 * `2026_10_02_000002_add_state_id_to_central_tables.php` gave users, fest_state_programs and
 * state_remittances a state_id so EnsureStateAdmin could fail closed, but skipped `tenants`.
 * The consequence: a state admin cannot be shown "the Sahodayas in my state" at all without
 * joining through fest_state_programs — which only covers Sahodayas already attached to a
 * published program, not the master list. Promotion (Phase 2) needs to list and act on the
 * whole list, so the tenant itself has to know its state.
 *
 * Nullable on purpose: every existing tenant predates multi-state, and a null state_id keeps
 * behaving exactly as today (superadmin sees it, state-scoped queries do not).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->uuid('state_id')->nullable()->after('type');
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->index('state_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropIndex(['state_id']);
            $table->dropColumn('state_id');
        });
    }
};
