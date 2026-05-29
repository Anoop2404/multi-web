<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_results', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedTinyInteger('class'); // 10 or 12
            $table->string('academic_year'); // e.g. 2024-25
            $table->unsignedSmallInteger('total_appeared')->default(0);
            $table->unsignedSmallInteger('pass_count')->default(0);
            $table->decimal('pass_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('distinctions')->default(0);
            $table->unsignedSmallInteger('first_class')->default(0);
            $table->jsonb('subject_stats')->nullable(); // per-subject pass%, top score
            $table->timestamps();
            $table->unique(['tenant_id', 'class', 'academic_year']);
        });

        Schema::create('toppers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_result_id');
            $table->foreign('board_result_id')->references('id')->on('board_results')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('photo')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->unsignedSmallInteger('total_marks')->nullable();
            $table->unsignedSmallInteger('marks_obtained')->nullable();
            $table->jsonb('subject_marks')->nullable(); // {English: 98, Maths: 100, ...}
            $table->boolean('is_perfect_scorer')->default(false); // scored 100 in a subject
            $table->string('stream')->nullable(); // Science | Commerce | Humanities (for class 12)
            $table->unsignedInteger('rank')->default(1);
            $table->timestamps();
            $table->index(['tenant_id', 'board_result_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toppers');
        Schema::dropIfExists('board_results');
    }
};
