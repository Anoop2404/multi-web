<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id'); // Sahodaya cluster that owns the region
                $table->string('name');
                $table->string('code', 64);
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
                $table->index('tenant_id');
            });
        }

        if (! Schema::hasTable('school_region_assignments')) {
            Schema::create('school_region_assignments', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id'); // Sahodaya cluster
                $table->unsignedBigInteger('region_id');
                $table->string('school_id');
                $table->string('academic_year', 10);
                $table->enum('source', ['school', 'sahodaya'])->default('sahodaya');
                $table->unsignedBigInteger('assigned_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
                $table->unique(['school_id', 'academic_year']);
                $table->index(['tenant_id', 'academic_year']);
                $table->index('region_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_region_assignments');
        Schema::dropIfExists('regions');
    }
};
