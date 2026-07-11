<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_invoices')) {
            return;
        }

        Schema::create('training_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id');
            $table->string('school_id')->nullable();
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('school_fee_id')->nullable();
            $table->string('invoice_number', 64)->unique();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 16)->default('draft'); // draft|issued|paid
            $table->timestamp('issued_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('training_programs')->cascadeOnDelete();
            $table->foreign('registration_id')->references('id')->on('training_registrations')->nullOnDelete();
            $table->foreign('school_fee_id')->references('id')->on('training_school_fees')->nullOnDelete();

            $table->index(['program_id', 'school_id']);
            $table->index('registration_id');
            $table->index('school_fee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_invoices');
    }
};
