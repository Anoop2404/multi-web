<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_sports_age_group_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('group_key', 20);
            $table->string('label');
            $table->unsignedTinyInteger('under_age')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('default_fee', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'group_key']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_sports_age_group_configs');
    }
};
