<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_configs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('layout_variant')->default('logo-left');
            $table->jsonb('items')->nullable(); // menu tree
            $table->timestamps();
        });

        Schema::create('footer_configs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('layout_variant')->default('three-column');
            $table->jsonb('content')->nullable(); // blocks: links, social, copyright
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->jsonb('meta')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('footer_configs');
        Schema::dropIfExists('nav_configs');
    }
};
