<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcq_grade_masters', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });

        Schema::create('mcq_grade_bands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grade_master_id');
            $table->foreign('grade_master_id')->references('id')->on('mcq_grade_masters')->cascadeOnDelete();
            $table->string('label', 20);
            $table->decimal('min_percentage', 5, 2)->default(0);
            $table->decimal('max_percentage', 5, 2)->default(100);
            $table->boolean('is_pass')->default(true);
            $table->boolean('rank_eligible')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('mcq_hall_ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->json('design_json')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });

        Schema::create('mcq_certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->json('design_json')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });

        Schema::table('mcq_exams', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_exams', 'grade_master_id')) {
                $table->unsignedBigInteger('grade_master_id')->nullable()->after('settings_json');
            }
            if (! Schema::hasColumn('mcq_exams', 'hall_ticket_template_id')) {
                $table->unsignedBigInteger('hall_ticket_template_id')->nullable()->after('grade_master_id');
            }
            if (! Schema::hasColumn('mcq_exams', 'certificate_template_id')) {
                $table->unsignedBigInteger('certificate_template_id')->nullable()->after('hall_ticket_template_id');
            }
        });

        Schema::create('mcq_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('mcq_registrations')->cascadeOnDelete();
            $table->unsignedBigInteger('certificate_template_id')->nullable();
            $table->json('design_snapshot_json')->nullable();
            $table->string('file_path')->nullable();
            $table->string('verification_uuid', 64)->unique();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique('registration_id');
        });

        if (Schema::hasColumn('mcq_marks', 'grade')) {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE mcq_marks MODIFY grade VARCHAR(20) NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcq_certificates');
        Schema::table('mcq_exams', function (Blueprint $table) {
            foreach (['certificate_template_id', 'hall_ticket_template_id', 'grade_master_id'] as $col) {
                if (Schema::hasColumn('mcq_exams', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('mcq_certificate_templates');
        Schema::dropIfExists('mcq_hall_ticket_templates');
        Schema::dropIfExists('mcq_grade_bands');
        Schema::dropIfExists('mcq_grade_masters');
    }
};
