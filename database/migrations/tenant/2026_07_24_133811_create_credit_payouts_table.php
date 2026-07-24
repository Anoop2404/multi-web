<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('school_id');
            $table->string('creditable_type');
            $table->unsignedBigInteger('creditable_id');
            $table->decimal('amount', 10, 2);
            $table->string('bank_ref')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['creditable_type', 'creditable_id']);
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_payouts');
    }
};
