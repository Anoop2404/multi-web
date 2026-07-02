<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('event_type', 30);
            $table->string('catalog_key', 120);
            $table->string('source', 20)->default('cksc'); // cksc | custom
            $table->boolean('is_enabled')->default(true);
            $table->boolean('fee_enabled')->default(false);
            $table->string('title');
            $table->string('item_code', 20)->nullable();
            $table->string('category', 30)->default('general');
            $table->string('stage_type', 20)->nullable();
            $table->string('venue_type', 20)->nullable();
            $table->string('competition_format', 30)->nullable();
            $table->string('sport_discipline', 40)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->json('criteria_json')->nullable();
            $table->string('participant_type', 20)->default('individual');
            $table->string('gender', 20)->default('open');
            $table->string('class_group', 20)->nullable();
            $table->string('age_group', 20)->nullable();
            $table->string('kids_band', 20)->nullable();
            $table->unsignedSmallInteger('max_per_school')->nullable();
            $table->unsignedSmallInteger('min_group_size')->nullable();
            $table->unsignedSmallInteger('max_group_size')->nullable();
            $table->unsignedSmallInteger('qualify_count')->nullable();
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'event_type', 'catalog_key']);
            $table->index(['tenant_id', 'event_type', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_catalog_items');
    }
};
