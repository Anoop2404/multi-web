<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scopes a Sahodaya staff member to specific regions for Membership/Student data — independent
 * of the Fest-only region_admin/FestEventStaff mechanism (EventRegionAdminScope), which stays
 * untouched. See Phase 4 of the Sahodaya admin credentials plan. Row presence alone scopes the
 * user; no rows means unrestricted, same as a plain sahodaya_admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_region_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('region_id');
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_region_assignments');
    }
};
