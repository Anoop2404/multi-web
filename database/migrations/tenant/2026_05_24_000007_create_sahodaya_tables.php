<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_bearers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('role'); // President | General Secretary | Treasurer | Vice President | Joint Secretary
            $table->string('school_name')->nullable();
            $table->string('photo')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('term_from')->nullable(); // e.g. 2024
            $table->string('term_to')->nullable();   // e.g. 2025
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('circulars', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->string('circular_number')->nullable();
            $table->string('file_path');
            $table->string('category')->default('general'); // general|kalotsav|sports|exam|meeting|other
            $table->date('issued_date');
            $table->string('academic_year')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'category', 'issued_date']);
        });

        Schema::create('kalotsav_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id'); // The sahodaya that owns this event
            $table->string('name'); // Kalotsav 2025 | Athletic Meet 2025 | Talent Search 2025
            $table->string('type')->default('kalotsav'); // kalotsav|athletic_meet|talent_search|other
            $table->string('academic_year');
            $table->date('event_date')->nullable();
            $table->string('venue')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('results_published')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'type', 'academic_year']);
        });

        Schema::create('kalotsav_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kalotsav_event_id');
            $table->foreign('kalotsav_event_id')->references('id')->on('kalotsav_events')->cascadeOnDelete();
            $table->string('name'); // Solo Dance | Group Song | Mono Act | Drawing | Quiz etc.
            $table->string('group')->nullable(); // Junior | Senior | Sub-Junior
            $table->unsignedTinyInteger('max_points')->default(5);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('kalotsav_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kalotsav_event_id');
            $table->foreign('kalotsav_event_id')->references('id')->on('kalotsav_events')->cascadeOnDelete();
            $table->unsignedBigInteger('kalotsav_category_id')->nullable();
            $table->foreign('kalotsav_category_id')->references('id')->on('kalotsav_categories')->nullOnDelete();
            $table->string('school_tenant_id'); // The school that participated
            $table->string('school_name'); // Denormalised for display
            $table->unsignedTinyInteger('position')->nullable(); // 1, 2, 3
            $table->unsignedSmallInteger('points')->default(0);
            $table->string('grade')->nullable(); // A | B | C
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['kalotsav_event_id', 'kalotsav_category_id', 'school_tenant_id'], 'kalotsav_results_unique');
            $table->index(['kalotsav_event_id', 'school_tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalotsav_results');
        Schema::dropIfExists('kalotsav_categories');
        Schema::dropIfExists('kalotsav_events');
        Schema::dropIfExists('circulars');
        Schema::dropIfExists('office_bearers');
    }
};
