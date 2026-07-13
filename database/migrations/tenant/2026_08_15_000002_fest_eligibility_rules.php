<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD-08 Phase 3: Data-driven eligibility rules (event / area / item scoped).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_eligibility_rules')) {
            Schema::create('fest_eligibility_rules', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('scope_type', 20); // event|area|item
                $table->unsignedBigInteger('scope_id');
                $table->string('rule_type', 40);
                $table->string('operator', 20)->default('in');
                $table->json('value_json')->nullable();
                $table->unsignedInteger('logic_group')->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'scope_type', 'scope_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_eligibility_rules');
    }
};
