<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mcq_paper_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('title');
            $table->unsignedInteger('version')->default(1);
            $table->json('definition_json');
            $table->json('questions_json');
            $table->decimal('total_marks', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('mcq_exam_paper_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id')->index();
            $table->json('mapping_json');
            $table->json('questions_json');
            $table->timestamps();
        });
        Schema::table('mcq_registrations', function (Blueprint $table) {
            $table->json('paper_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mcq_registrations', fn (Blueprint $table) => $table->dropColumn('paper_snapshot'));
        Schema::dropIfExists('mcq_exam_paper_assignments');
        Schema::dropIfExists('mcq_paper_templates');
    }
};
