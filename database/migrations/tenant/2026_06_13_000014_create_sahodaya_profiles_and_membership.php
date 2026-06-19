<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sahodaya_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->string('slug')->nullable();
            $table->string('prefix', 10)->nullable();
            $table->string('cbse_region')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->enum('student_data_mode', ['full_records', 'counts_only', 'not_required'])->default('not_required');
            $table->enum('membership_fee_type', ['fixed', 'variable_by_student_count'])->default('fixed');
            $table->decimal('fixed_membership_fee_amount', 10, 2)->nullable();
            $table->boolean('teacher_registration_enabled')->default(false);
            $table->text('payment_instructions')->nullable();
            $table->boolean('prefixes_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('membership_fee_slabs', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id');
            $table->string('academic_year', 10);
            $table->unsignedInteger('min_students');
            $table->unsignedInteger('max_students')->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index(['sahodaya_id', 'academic_year']);
        });

        Schema::create('sahodaya_registration_windows', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id');
            $table->string('academic_year', 10);
            $table->date('registration_starts_at')->nullable();
            $table->date('registration_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['sahodaya_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sahodaya_registration_windows');
        Schema::dropIfExists('membership_fee_slabs');
        Schema::dropIfExists('sahodaya_profiles');
    }
};
