<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of docs/STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md.
 *
 * external_sahodayas / external_schools were built as the deliberately tenant-less intake for
 * Sahodayas outside the platform (see 2026_08_03_000001_external_sahodaya_intake.php). They are
 * now also the seeded master list of the state's Sahodayas, and the ones that will run their own
 * Kalotsav get promoted to real tenants — subdomain + dedicated database, schools underneath.
 *
 * `tenant_id` is that link, and it is what makes the whole pipeline idempotent: non-null means
 * "already promoted", so a re-run skips instead of creating a second tenant and a second
 * database for the same Sahodaya. It is also what the access-code portal checks before allowing
 * a write, so a promoted Sahodaya's roster cannot fork across two systems.
 *
 * promotion_status/promotion_error/promoted_at exist because promotion is a 10-step sequence that
 * can fail halfway (a database that cannot be created, a subdomain collision). Recording where it
 * stopped is what lets a batch continue past one bad row and lets the state admin retry just that
 * Sahodaya instead of the whole district.
 *
 * state_id mirrors the tenants.state_id added alongside this: the master list has to be listable
 * per state before any of it is promoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_sahodayas', function (Blueprint $table) {
            $table->uuid('state_id')->nullable()->after('state_program_id');
            $table->string('tenant_id')->nullable()->after('state_id');
            // not_started | queued | provisioning | ready | failed
            $table->string('promotion_status', 20)->default('not_started')->after('source');
            $table->text('promotion_error')->nullable()->after('promotion_status');
            $table->timestamp('promoted_at')->nullable()->after('promotion_error');

            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('promotion_status');
        });

        Schema::table('external_schools', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('external_sahodaya_id');
            $table->timestamp('promoted_at')->nullable()->after('is_appeal_pool');

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('external_schools', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['tenant_id', 'promoted_at']);
        });

        Schema::table('external_sahodayas', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['promotion_status']);
            $table->dropColumn(['state_id', 'tenant_id', 'promotion_status', 'promotion_error', 'promoted_at']);
        });
    }
};
