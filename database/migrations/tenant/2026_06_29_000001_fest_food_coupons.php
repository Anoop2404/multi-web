<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_food_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('school_id');
            $table->string('coupon_code', 20)->unique();
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snacks'])->default('lunch');
            $table->date('valid_date');
            $table->unsignedSmallInteger('head_count')->default(1);
            $table->enum('status', ['issued', 'redeemed', 'void'])->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->index(['event_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_food_coupons');
    }
};
