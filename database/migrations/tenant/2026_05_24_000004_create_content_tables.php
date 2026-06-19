<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->string('slug');
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->string('category')->default('general'); // general|announcement|circular|achievement
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'published_at']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('venue')->nullable();
            $table->boolean('is_upcoming')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'start_date']);
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('album_id');
            $table->foreign('album_id')->references('id')->on('gallery_albums')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->string('image_path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('designation');
            $table->string('department')->nullable();
            $table->string('qualification')->nullable();
            $table->string('photo')->nullable();
            $table->string('type')->default('teaching'); // teaching|non-teaching|admin
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'type', 'display_order']);
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('category')->default('academic'); // academic|sports|cultural|national|other
            $table->string('level')->default('school'); // school|district|state|national|international
            $table->date('achieved_at')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'category', 'level']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('designation')->nullable(); // Parent | Alumni | Student | Principal
            $table->string('photo')->nullable();
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->unsignedSmallInteger('batch_year')->nullable();
            $table->string('current_role')->nullable();
            $table->string('current_organisation')->nullable();
            $table->string('photo')->nullable();
            $table->text('message')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'is_approved', 'batch_year']);
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('file_size')->nullable();
            $table->string('category')->default('other'); // booklist|calendar|circular|question_paper|annual_report|form|minutes|other
            $table->string('academic_year')->nullable(); // e.g. 2024-25
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'category', 'academic_year']);
        });

        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('qualification')->nullable();
            $table->string('experience')->nullable();
            $table->date('last_date')->nullable();
            $table->string('apply_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active', 'last_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_vacancies');
        Schema::dropIfExists('downloads');
        Schema::dropIfExists('alumni');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('staff_members');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('events');
        Schema::dropIfExists('news_articles');
    }
};
