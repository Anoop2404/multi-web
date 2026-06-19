<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('student_name');
            $table->date('dob')->nullable();
            $table->string('class_applying'); // LKG | Class 1 | Class 11 - Science etc.
            $table->string('parent_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new'); // new|reviewed|shortlisted|rejected
            $table->text('admin_notes')->nullable();
            $table->string('academic_year')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('tc_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('student_name');
            $table->string('class');
            $table->string('division')->nullable();
            $table->date('dob')->nullable();
            $table->string('admission_number')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('parent_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending|processing|ready|issued
            $table->text('admin_notes')->nullable();
            $table->date('issued_date')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tc_requests');
        Schema::dropIfExists('admission_enquiries');
    }
};
