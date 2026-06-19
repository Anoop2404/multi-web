<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name'); // e.g. 2025-26
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_current']);
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name'); // LKG, UKG, 1, 2, ... 12
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('school_class_id');
            $table->foreign('school_class_id')->references('id')->on('school_classes')->cascadeOnDelete();
            $table->string('name'); // A, B, C
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_class_id', 'name']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->unsignedBigInteger('school_class_id');
            $table->foreign('school_class_id')->references('id')->on('school_classes')->cascadeOnDelete();
            $table->unsignedBigInteger('class_section_id');
            $table->foreign('class_section_id')->references('id')->on('class_sections')->cascadeOnDelete();
            $table->string('admission_number');
            $table->string('roll_number')->nullable();
            $table->string('name');
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('parent_name')->nullable();
            $table->string('parent_phone', 30)->nullable();
            $table->string('parent_email')->nullable();
            $table->text('address')->nullable();
            $table->date('admission_date')->nullable();
            $table->enum('status', ['active', 'transferred', 'graduated', 'withdrawn'])->default('active');
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'academic_year_id', 'admission_number']);
            $table->index(['tenant_id', 'academic_year_id', 'school_class_id', 'class_section_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('class_sections');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('academic_years');
    }
};
