<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_categories', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->nullable();
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label');
            $table->unsignedTinyInteger('min_class')->nullable();
            $table->unsignedTinyInteger('max_class')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'code']);
        });

        Schema::create('class_category_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id');
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('class_category_id');
            $table->foreign('class_category_id')->references('id')->on('class_categories')->cascadeOnDelete();
            $table->boolean('is_hidden')->default(true);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'class_category_id']);
        });

        Schema::create('teaching_types', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->nullable();
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'code']);
        });

        Schema::create('teaching_type_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id');
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('teaching_type_id');
            $table->foreign('teaching_type_id')->references('id')->on('teaching_types')->cascadeOnDelete();
            $table->boolean('is_hidden')->default(true);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'teaching_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_type_overrides');
        Schema::dropIfExists('teaching_types');
        Schema::dropIfExists('class_category_overrides');
        Schema::dropIfExists('class_categories');
    }
};
