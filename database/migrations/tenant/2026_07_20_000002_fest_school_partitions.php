<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_event_school_partitions')) {
            return;
        }

        Schema::create('fest_event_school_partitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->string('school_id');
            $table->string('partition_key', 64);
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'school_id']);
            $table->index(['event_id', 'partition_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_event_school_partitions');
    }
};
