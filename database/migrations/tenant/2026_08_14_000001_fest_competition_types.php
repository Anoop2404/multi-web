<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_competition_types')) {
            Schema::create('fest_competition_types', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('type_key', 40);
                $table->string('label');
                $table->string('nav_slug', 60)->nullable();
                $table->string('route_prefix', 60)->nullable();
                $table->string('icon', 40)->nullable();
                $table->string('description')->nullable();
                $table->boolean('is_singleton')->default(true);
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(100);
                $table->boolean('is_active')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'type_key']);
                $table->index(['tenant_id', 'is_active', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_competition_types');
    }
};
