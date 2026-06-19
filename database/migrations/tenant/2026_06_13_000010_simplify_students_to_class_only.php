<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'academic_year_id', 'admission_number']);
            $table->dropForeign(['class_section_id']);
            $table->dropForeign(['academic_year_id']);
            $table->dropIndex(['tenant_id', 'academic_year_id', 'school_class_id', 'class_section_id']);
            $table->dropColumn(['class_section_id', 'academic_year_id']);
        });

        Schema::dropIfExists('class_sections');
        Schema::dropIfExists('academic_years');

        Schema::table('students', function (Blueprint $table) {
            $table->unique(['tenant_id', 'admission_number']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'admission_number']);
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('school_class_id');
            $table->foreign('school_class_id')->references('id')->on('school_classes')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_class_id', 'name']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('class_section_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('class_section_id')->references('id')->on('class_sections')->cascadeOnDelete();
        });
    }
};
