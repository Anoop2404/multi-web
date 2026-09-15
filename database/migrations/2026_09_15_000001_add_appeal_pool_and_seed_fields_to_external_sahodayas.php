<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports three additions to the outside-Sahodaya intake (docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1):
 * - Bulk-seeding from the official state Sahodaya list (district, source).
 * - One "Appeal Sahodaya" per state program and one "Appeal School" per external Sahodaya —
 *   is_appeal_pool mirrors the existing tenants.is_appeal_pool flag one/two levels up.
 * - Fee collection is out of scope for the appeal pool rows (no registration fee applies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_sahodayas', function (Blueprint $table) {
            $table->string('district')->nullable()->after('name');
            $table->boolean('is_appeal_pool')->default(false)->after('status');
            $table->string('source', 20)->default('manual')->after('is_appeal_pool'); // manual | seeded
        });

        Schema::table('external_schools', function (Blueprint $table) {
            $table->boolean('is_appeal_pool')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('external_schools', function (Blueprint $table) {
            $table->dropColumn('is_appeal_pool');
        });

        Schema::table('external_sahodayas', function (Blueprint $table) {
            $table->dropColumn(['district', 'is_appeal_pool', 'source']);
        });
    }
};
