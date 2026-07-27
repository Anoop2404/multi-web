<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('user_region_assignments');
    }
};
