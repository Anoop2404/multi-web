<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_school_event_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->string('school_id');
            $table->decimal('school_registration_fee', 10, 2)->default(0);
            $table->unsignedSmallInteger('participation_item_count')->default(0);
            $table->decimal('participation_fee', 10, 2)->default(0);
            $table->decimal('total_due', 10, 2)->default(0);
            $table->unsignedBigInteger('fee_receipt_id')->nullable();
            $table->foreign('fee_receipt_id')->references('id')->on('fee_receipts')->nullOnDelete();
            $table->enum('status', ['pending', 'proof_uploaded', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->unique(['event_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_school_event_fees');
    }
};
