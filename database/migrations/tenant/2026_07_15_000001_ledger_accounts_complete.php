<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_heads', function (Blueprint $table) {
            if (! Schema::hasColumn('account_heads', 'mcq_exam_id')) {
                $table->unsignedBigInteger('mcq_exam_id')->nullable()->after('event_id');
                $table->index(['tenant_id', 'mcq_exam_id']);
            }
        });

        if (! Schema::hasTable('ledger_opening_balances')) {
            Schema::create('ledger_opening_balances', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('financial_year_id');
                $table->unsignedBigInteger('account_head_id');
                $table->enum('entry_type', ['debit', 'credit']);
                $table->decimal('amount', 14, 2);
                $table->string('notes')->nullable();
                $table->uuid('journal_id')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'financial_year_id', 'account_head_id'], 'ledger_opening_balances_unique');
                $table->foreign('account_head_id')->references('id')->on('account_heads')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('sahodaya_payables')) {
            Schema::create('sahodaya_payables', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('financial_year_id')->nullable();
                $table->string('vendor_name');
                $table->string('description')->nullable();
                $table->decimal('amount', 14, 2);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->date('due_date')->nullable();
                $table->date('incurred_date')->nullable();
                $table->enum('status', ['pending', 'partial', 'paid', 'cancelled'])->default('pending');
                $table->unsignedBigInteger('expense_head_id')->nullable();
                $table->uuid('obligation_journal_id')->nullable();
                $table->uuid('payment_journal_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->foreign('expense_head_id')->references('id')->on('account_heads')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sahodaya_payables');
        Schema::dropIfExists('ledger_opening_balances');

        Schema::table('account_heads', function (Blueprint $table) {
            if (Schema::hasColumn('account_heads', 'mcq_exam_id')) {
                $table->dropColumn('mcq_exam_id');
            }
        });
    }
};
