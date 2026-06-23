<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_heads', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('financial_year_id')->nullable();
            $table->foreign('financial_year_id')->references('id')->on('financial_years')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->enum('type', ['income', 'expense', 'asset', 'liability'])->default('income');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('financial_year_id')->nullable();
            $table->foreign('financial_year_id')->references('id')->on('financial_years')->nullOnDelete();
            $table->unsignedBigInteger('account_head_id');
            $table->foreign('account_head_id')->references('id')->on('account_heads')->cascadeOnDelete();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->enum('entry_type', ['debit', 'credit'])->default('credit');
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->date('transaction_date');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'transaction_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('account_heads');
    }
};
