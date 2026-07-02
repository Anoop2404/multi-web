<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_transactions', 'journal_id')) {
                $table->uuid('journal_id')->nullable()->after('tenant_id');
                $table->index('journal_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_transactions', 'journal_id')) {
                $table->dropIndex(['journal_id']);
                $table->dropColumn('journal_id');
            }
        });
    }
};
