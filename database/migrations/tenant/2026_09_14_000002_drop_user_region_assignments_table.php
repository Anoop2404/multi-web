<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the dead region-scoping mechanism: user_region_assignments was only ever read by
 * RegionScope middleware, which was never registered on any route, and nothing ever wrote rows
 * into this table. Region-admin scoping is now handled via FestEventStaff (duty=region_admin,
 * region_id) — see docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.3, Phase 2.
 *
 * down() recreates the table (matching the original 2026_09_07_000001 migration) rather than
 * restoring any data — there was none to restore, since nothing ever populated it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_region_assignments');
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_region_assignments')) {
            Schema::create('user_region_assignments', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('region_id');
                $table->string('academic_year', 20);
                $table->unsignedBigInteger('assigned_by_user_id')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id', 'region_id', 'academic_year'], 'ura_tenant_user_region_year_unique');

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
            });
        }
    }
};
