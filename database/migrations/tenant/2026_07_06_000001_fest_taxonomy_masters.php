<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_taxonomy_masters')) {
            Schema::create('fest_taxonomy_masters', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('dimension', 40);
                $table->string('entry_key', 60);
                $table->string('label');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'dimension', 'entry_key']);
                $table->index(['tenant_id', 'dimension', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_taxonomy_masters');
    }
};
