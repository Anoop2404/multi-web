<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_section_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('site_id'); // website_sites uses bigint PK
            $table->foreignId('site_section_id');

            // Item position within its repeater
            $table->string('item_key');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('display_order')->default(0);

            // Content — stored as JSON for flexibility
            $table->json('data')->nullable();    // sub-fields values
            $table->json('meta')->nullable();    // _enabled, _featured, _start_date, _end_date

            // Visibility constraints
            $table->dateTime('visible_from')->nullable();
            $table->dateTime('visible_until')->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('website_sites')->cascadeOnDelete();
            $table->foreign('site_section_id')->references('id')->on('site_sections')->cascadeOnDelete();

            // Efficient lookups: all items for a section+key, ordered
            $table->index(['site_section_id', 'item_key', 'sort_order']);
            // Filter active items quickly
            $table->index(['site_section_id', 'item_key', 'is_enabled', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_section_items');
    }
};
