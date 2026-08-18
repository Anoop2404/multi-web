<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD-13 §9 Feature Management + §8 Licensing limits. Replaces SubscriptionPlan.features'
 * unstructured JSON (confirmed unused by any UI) with a real, queryable structure: plan
 * defaults + per-tenant overrides, so FeatureGate can answer "does this tenant's plan
 * include X" without every caller having to parse a freeform JSON blob differently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->string('feature_key', 60);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });

        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('feature_key', 60);
            $table->boolean('enabled')->nullable();
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_overrides');
        Schema::dropIfExists('plan_features');
    }
};
