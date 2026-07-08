<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_heads', function (Blueprint $table) {
            if (! Schema::hasColumn('account_heads', 'training_program_id')) {
                $table->unsignedBigInteger('training_program_id')->nullable()->after('mcq_exam_id');
                $table->index(['tenant_id', 'training_program_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_heads', function (Blueprint $table) {
            if (Schema::hasColumn('account_heads', 'training_program_id')) {
                $table->dropColumn('training_program_id');
            }
        });
    }
};
