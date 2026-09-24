<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_school_distances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->string('school_id');
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_school_distances');
    }
};
