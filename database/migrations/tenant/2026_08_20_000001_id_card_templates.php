<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('id_card_templates')) {
            Schema::create('id_card_templates', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('event_id')->nullable();
                $table->foreign('event_id')->references('id')->on('fest_events')->nullOnDelete();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->foreign('item_id')->references('id')->on('fest_event_items')->nullOnDelete();
                $table->string('audience')->nullable(); // student, volunteer, staff — null = all
                $table->string('title')->nullable();
                $table->string('background_path')->nullable();
                $table->unsignedSmallInteger('card_width_mm')->default(96);
                $table->unsignedSmallInteger('card_height_mm')->default(72);
                $table->unsignedTinyInteger('cards_per_page')->default(4);
                $table->json('layout_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'event_id', 'item_id', 'audience']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('id_card_templates');
    }
};
