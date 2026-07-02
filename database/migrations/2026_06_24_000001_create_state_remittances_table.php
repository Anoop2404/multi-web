<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id');        // target Sahodaya tenant ID
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->string('academic_year', 20)->nullable();
            $table->enum('status', ['pending', 'submitted', 'verified', 'rejected'])->default('pending');
            $table->string('proof_path')->nullable();
            $table->string('transaction_ref')->nullable();
            $table->string('bank_name')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['sahodaya_id', 'status']);
            $table->index('academic_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_remittances');
    }
};
