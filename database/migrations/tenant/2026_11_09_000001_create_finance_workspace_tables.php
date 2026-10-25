<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_payables', function (Blueprint $t) {
            $t->string('submission_key', 64)->nullable();
            $t->unique(['tenant_id', 'submission_key'], 'payable_submission_unique');
        });
        Schema::create('finance_payment_requests', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id');
            $t->string('submission_key', 64);
            $t->unsignedBigInteger('payable_id');
            $t->string('journal_id');
            $t->timestamps();
            $t->unique(['tenant_id', 'submission_key']);
        });
        Schema::create('finance_vendors', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'name']);
        });
        Schema::create('finance_documents', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id')->index();
            $t->string('submission_key', 64);
            $t->string('kind');
            $t->string('status')->default('pending');
            $t->unsignedBigInteger('financial_year_id')->nullable();
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->unsignedBigInteger('account_head_id')->nullable();
            $t->unsignedBigInteger('bank_account_id')->nullable();
            $t->unsignedBigInteger('destination_bank_id')->nullable();
            $t->unsignedBigInteger('event_id')->nullable();
            $t->string('region')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->decimal('amount', 14, 2);
            $t->date('document_date');
            $t->string('reference')->nullable();
            $t->text('description')->nullable();
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->string('journal_id')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->text('review_reason')->nullable();
            $t->date('next_due')->nullable();
            $t->boolean('recurring')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'submission_key']);
        });
        Schema::create('finance_budgets', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id');
            $t->unsignedBigInteger('financial_year_id');
            $t->unsignedBigInteger('account_head_id');
            $t->unsignedBigInteger('event_id')->default(0);
            $t->decimal('amount', 14, 2);
            $t->timestamps();
            $t->unique(['tenant_id', 'financial_year_id', 'account_head_id', 'event_id'], 'finance_budget_scope_unique');
        });
        Schema::create('finance_statement_lines', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id');
            $t->unsignedBigInteger('bank_account_id');
            $t->string('import_key', 64);
            $t->date('transaction_date');
            $t->string('description');
            $t->decimal('amount', 14, 2);
            $t->unsignedBigInteger('ledger_transaction_id')->nullable()->unique();
            $t->unsignedBigInteger('matched_by')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'import_key']);
        });
        Schema::create('finance_period_locks', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id');
            $t->unsignedBigInteger('financial_year_id');
            $t->unsignedBigInteger('carry_to_year_id')->nullable();
            $t->unsignedBigInteger('closed_by');
            $t->text('reason');
            $t->timestamps();
            $t->unique(['tenant_id', 'financial_year_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sahodaya_payables', function (Blueprint $t) {
            $t->dropUnique('payable_submission_unique');
            $t->dropColumn('submission_key');
        });
        foreach (['finance_payment_requests', 'finance_period_locks', 'finance_statement_lines', 'finance_budgets', 'finance_documents', 'finance_vendors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
