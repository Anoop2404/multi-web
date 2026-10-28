<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Scope question banks to a master class and/or class category for model-exam papers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_question_banks', function (Blueprint $table) {
            $table->unsignedBigInteger('class_category_id')->nullable()->after('class_group');
            $table->unsignedBigInteger('master_class_id')->nullable()->after('class_category_id');
            $table->unsignedBigInteger('source_bank_id')->nullable()->after('master_class_id');
            $table->string('contributed_by_school_id', 36)->nullable()->after('source_bank_id');
            $table->index(['sahodaya_id', 'class_category_id'], 'mcq_banks_sahodaya_category_idx');
            $table->index(['sahodaya_id', 'master_class_id'], 'mcq_banks_sahodaya_class_idx');
            $table->index(['source_bank_id'], 'mcq_banks_source_bank_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mcq_question_banks', function (Blueprint $table) {
            $table->dropIndex('mcq_banks_sahodaya_category_idx');
            $table->dropIndex('mcq_banks_sahodaya_class_idx');
            $table->dropIndex('mcq_banks_source_bank_idx');
            $table->dropColumn(['class_category_id', 'master_class_id', 'source_bank_id', 'contributed_by_school_id']);
        });
    }
};
