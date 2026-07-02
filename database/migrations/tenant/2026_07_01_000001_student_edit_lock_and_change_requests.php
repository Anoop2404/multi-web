<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sahodaya_profiles')) {
            Schema::table('sahodaya_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('sahodaya_profiles', 'student_edit_lock_enabled')) {
                    $table->boolean('student_edit_lock_enabled')->default(false)->after('teacher_registration_enabled');
                }
                if (! Schema::hasColumn('sahodaya_profiles', 'student_edit_lock_at')) {
                    $table->timestamp('student_edit_lock_at')->nullable()->after('student_edit_lock_enabled');
                }
            });
        }

        if (! Schema::hasTable('student_edit_change_requests')) {
            Schema::create('student_edit_change_requests', function (Blueprint $table) {
                $table->id();
                $table->string('school_id');
                $table->unsignedBigInteger('student_id')->nullable();
                $table->enum('change_type', ['update', 'create'])->default('update');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->json('changes_json');
                $table->string('photo_path')->nullable();
                $table->text('reason');
                $table->text('resolution_note')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'status']);
                $table->index(['student_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_edit_change_requests');

        if (Schema::hasTable('sahodaya_profiles')) {
            Schema::table('sahodaya_profiles', function (Blueprint $table) {
                foreach (['student_edit_lock_at', 'student_edit_lock_enabled'] as $col) {
                    if (Schema::hasColumn('sahodaya_profiles', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
