<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the State Kalotsav module: one canonical identity per participating Sahodaya.
 *
 * Until now every State operational row keyed on state_qualifier_intakes.source_tenant_id, a
 * polymorphic string that is either a central tenant uuid ("managed") or "external:{uuid}" for a
 * Sahodaya that is not on the platform. That has two consequences the State module cannot live with:
 *
 *  1. Promotion changes a Sahodaya's identity. The moment an outside Sahodaya becomes a tenant, its
 *     new submissions key on the tenant uuid while its history stays under "external:{uuid}" — the
 *     same body counted twice in standings, slot usage, fees and every report.
 *  2. Names had to be read live from the central `tenants` / `external_sahodayas` tables, which the
 *     State module is explicitly not allowed to depend on, and which lose the name entirely if a
 *     tenant is renamed or removed after the event.
 *
 * This table is the State's own directory: one row per Sahodaya per state, carrying the name at the
 * time it took part, and pointing at whichever central record(s) it maps to. Promotion attaches
 * tenant_id to the SAME row rather than creating a second one, so history and future submissions
 * stay on one identity.
 *
 * Lives on the state connection on purpose — State services query State data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_sahodayas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('state_id')->nullable()->index();

            // Snapshot, not a lookup: what this Sahodaya was called when it took part.
            $table->string('name');
            $table->string('code', 40)->nullable();
            $table->string('district')->nullable();

            // Whichever central records this identity maps to. Both may be set at once — that is
            // exactly a promoted external Sahodaya, and is the case this table exists to keep single.
            $table->string('tenant_id')->nullable();
            $table->uuid('external_sahodaya_id')->nullable();

            // How it first entered the State's world; it does not change when the Sahodaya is
            // later promoted, so "arrived as an outside Sahodaya" stays knowable.
            $table->string('origin', 20)->default('managed'); // managed | external
            $table->boolean('is_active')->default(true);
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();

            // One identity per central record per state. Postgres treats NULLs as distinct, so a
            // directory row with only one of the two links does not collide with others.
            $table->unique(['state_id', 'tenant_id']);
            $table->unique(['state_id', 'external_sahodaya_id']);
            $table->index('tenant_id');
            $table->index('external_sahodaya_id');
        });

        Schema::table('state_qualifier_intakes', function (Blueprint $table) {
            // Canonical directory id. source_tenant_id is deliberately left in place: it is the raw
            // thing the submitting side sent, and is still how an intake is matched on arrival.
            $table->uuid('sahodaya_id')->nullable()->after('source_tenant_id')->index();
            $table->string('sahodaya_name')->nullable()->after('sahodaya_id');
        });

        Schema::table('state_fest_registrations', function (Blueprint $table) {
            // sahodaya_id already exists here (2026_09_06_000001) as a denormalized copy of
            // source_tenant_id and was never backfilled; it now holds the canonical directory id.
            $table->string('sahodaya_name')->nullable()->after('sahodaya_id');
        });
    }

    public function down(): void
    {
        Schema::table('state_fest_registrations', function (Blueprint $table) {
            $table->dropColumn('sahodaya_name');
        });

        Schema::table('state_qualifier_intakes', function (Blueprint $table) {
            $table->dropColumn(['sahodaya_id', 'sahodaya_name']);
        });

        Schema::dropIfExists('state_sahodayas');
    }
};
