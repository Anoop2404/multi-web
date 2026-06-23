<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_houses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->string('motto')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fest_house_schools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('house_id');
            $table->foreign('house_id')->references('id')->on('fest_houses')->cascadeOnDelete();
            $table->string('school_id');
            $table->timestamps();

            $table->unique(['event_id', 'school_id']);
        });

        Schema::create('fest_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id')->references('id')->on('fest_participants')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fest_catering_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->string('school_id');
            $table->date('meal_date');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snacks'])->default('lunch');
            $table->unsignedSmallInteger('head_count')->default(0);
            $table->text('notes')->nullable();
            $table->enum('status', ['requested', 'confirmed', 'cancelled'])->default('requested');
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'school_id']);
        });

        Schema::table('fest_participants', function (Blueprint $table) {
            $table->timestamp('disqualified_at')->nullable()->after('chest_no');
            $table->string('disqualification_reason')->nullable()->after('disqualified_at');
        });
    }

    public function down(): void
    {
        Schema::table('fest_participants', function (Blueprint $table) {
            $table->dropColumn(['disqualified_at', 'disqualification_reason']);
        });

        Schema::dropIfExists('fest_catering_orders');
        Schema::dropIfExists('fest_appeals');
        Schema::dropIfExists('fest_house_schools');
        Schema::dropIfExists('fest_houses');
    }
};
