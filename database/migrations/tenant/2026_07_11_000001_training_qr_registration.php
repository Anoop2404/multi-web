<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_programs')) {
            Schema::table('training_programs', function (Blueprint $table) {
                if (! Schema::hasColumn('training_programs', 'qr_registration_token')) {
                    $table->string('qr_registration_token', 64)->nullable()->unique()->after('allow_teacher_self_registration');
                }
                if (! Schema::hasColumn('training_programs', 'qr_registration_enabled')) {
                    $table->boolean('qr_registration_enabled')->default(true)->after('qr_registration_token');
                }
                if (! Schema::hasColumn('training_programs', 'attendance_qr_token')) {
                    $table->string('attendance_qr_token', 64)->nullable()->unique()->after('qr_registration_enabled');
                }
            });
        }

        if (Schema::hasTable('training_sessions')) {
            Schema::table('training_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('training_sessions', 'attendance_token')) {
                    $table->string('attendance_token', 64)->nullable()->unique()->after('duration_minutes');
                }
            });
        }

        if (! Schema::hasTable('training_pending_schools')) {
            Schema::create('training_pending_schools', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('program_id');
                $table->foreign('program_id')->references('id')->on('training_programs')->cascadeOnDelete();
                $table->string('school_name');
                $table->string('school_code')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('status', 20)->default('pending');
                $table->string('linked_school_id')->nullable();
                $table->timestamps();

                $table->index(['program_id', 'status']);
            });
        }

        if (Schema::hasTable('training_registrations')) {
            Schema::table('training_registrations', function (Blueprint $table) {
                if (! Schema::hasColumn('training_registrations', 'registration_source')) {
                    $table->string('registration_source', 20)->default('school')->after('status');
                }
                if (! Schema::hasColumn('training_registrations', 'consent_at')) {
                    $table->timestamp('consent_at')->nullable()->after('registration_source');
                }
                if (! Schema::hasColumn('training_registrations', 'department')) {
                    $table->string('department', 120)->nullable()->after('consent_at');
                }
                if (! Schema::hasColumn('training_registrations', 'teacher_created')) {
                    $table->boolean('teacher_created')->default(false)->after('department');
                }
                if (! Schema::hasColumn('training_registrations', 'pending_school_id')) {
                    $table->unsignedBigInteger('pending_school_id')->nullable()->after('teacher_created');
                    $table->foreign('pending_school_id')
                        ->references('id')
                        ->on('training_pending_schools')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_registrations')) {
            Schema::table('training_registrations', function (Blueprint $table) {
                if (Schema::hasColumn('training_registrations', 'pending_school_id')) {
                    $table->dropForeign(['pending_school_id']);
                }
                foreach (['registration_source', 'consent_at', 'department', 'teacher_created', 'pending_school_id'] as $col) {
                    if (Schema::hasColumn('training_registrations', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('training_pending_schools');

        if (Schema::hasTable('training_sessions') && Schema::hasColumn('training_sessions', 'attendance_token')) {
            Schema::table('training_sessions', function (Blueprint $table) {
                $table->dropColumn('attendance_token');
            });
        }

        if (Schema::hasTable('training_programs')) {
            Schema::table('training_programs', function (Blueprint $table) {
                foreach (['qr_registration_token', 'qr_registration_enabled', 'attendance_qr_token'] as $col) {
                    if (Schema::hasColumn('training_programs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
