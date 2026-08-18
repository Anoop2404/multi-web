<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD-13 §5 Setup Wizard's "completion checklist" — tracks real, existing tenant
 * provisioning steps (TenantController's store/saveDatabase/migrateDatabase/
 * savePortalAdmin/uploadLogo) rather than a fictional wizard for steps this app
 * doesn't implement (number formats, email settings). Makes a half-configured
 * tenant visible instead of silently dangling, which is the actual problem today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_provisioning_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('step_key', 60);
            $table->timestamp('completed_at');
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_provisioning_checklists');
    }
};
