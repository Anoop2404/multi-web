<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_trophy_templates')) {
            Schema::create('fest_trophy_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->nullable()->constrained('fest_events')->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 50)->nullable();
                $table->string('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fest_trophies')) {
            Schema::create('fest_trophies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('fest_trophy_templates')->nullOnDelete();
                $table->unsignedSmallInteger('trophy_no');
                $table->string('title');
                $table->string('trophy_type', 30); // overall, category, item, item_group, individual_championship
                $table->unsignedSmallInteger('position')->default(1);
                $table->string('award_type', 20)->default('school'); // school, individual
                $table->string('category_key', 50)->nullable();
                $table->foreignId('item_id')->nullable()->constrained('fest_event_items')->nullOnDelete();
                $table->string('item_name_pattern')->nullable();
                $table->json('item_ids')->nullable();
                $table->string('item_group_name')->nullable();
                $table->string('gender', 10)->nullable();
                $table->string('notes')->nullable();
                $table->boolean('is_rolling')->default(false);
                $table->string('donor_name')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['event_id', 'trophy_no']);
                $table->index(['event_id', 'trophy_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_trophies');
        Schema::dropIfExists('fest_trophy_templates');
    }
};
