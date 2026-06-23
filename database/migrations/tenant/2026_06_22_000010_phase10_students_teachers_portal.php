<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('tenant_id');
                $table->index('user_id');
            }
            if (! Schema::hasColumn('students', 'academic_year_id')) {
                $table->unsignedBigInteger('academic_year_id')->nullable()->after('user_id');
                $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            }
            if (! Schema::hasColumn('students', 'reg_no')) {
                $table->string('reg_no', 30)->nullable()->after('academic_year_id');
                $table->index(['tenant_id', 'reg_no']);
            }
            if (! Schema::hasColumn('students', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('reg_no', 30)->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('designation')->nullable();
            $table->string('subject')->nullable();
            $table->unsignedBigInteger('teaching_type_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'reg_no']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');

        Schema::table('students', function (Blueprint $table) {
            foreach (['email', 'reg_no', 'academic_year_id', 'user_id'] as $col) {
                if (Schema::hasColumn('students', $col)) {
                    if ($col === 'academic_year_id') {
                        $table->dropForeign(['academic_year_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
